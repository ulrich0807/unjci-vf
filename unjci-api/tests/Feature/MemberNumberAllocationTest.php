<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\PreloadedMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MemberNumberAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_free_reported_number_is_kept_only_as_a_provisional_reservation(): void
    {
        $memberNumber = 'UJ25-54321';

        $response = $this->submitApplication([
            'requestType' => 'renewal',
            'currentMemberNumber' => $memberNumber,
            'email' => 'numero-provisoire@example.test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.request_type', 'renewal')
            ->assertJsonPath('data.current_member_number', $memberNumber)
            ->assertJsonPath('data.member_number', null)
            ->assertJsonPath('login_identifier', 'numero-provisoire@example.test');

        $member = Member::findOrFail($response->json('data.id'));

        $this->assertSame($memberNumber, $member->current_member_number);
        $this->assertNull($member->member_number);
        $this->assertStringStartsWith('pending-', $member->user->login);
        $this->assertNotSame($memberNumber, $member->user->login);
    }

    public function test_a_provisional_number_cannot_be_used_to_log_in_or_find_an_official_card(): void
    {
        $memberNumber = 'UJ25-54322';
        $email = 'connexion-provisoire@example.test';

        $this->submitApplication([
            'requestType' => 'renewal',
            'currentMemberNumber' => $memberNumber,
            'email' => $email,
        ])->assertCreated();

        $this->postJson('/login', [
            'login' => $memberNumber,
            'password' => 'password123',
        ])->assertUnauthorized();

        $this->getJson("/members/by-card/{$memberNumber}")
            ->assertNotFound();

        User::where('email', $email)->update(['email_verified_at' => now()]);
        $this->postJson('/login', [
            'login' => $email,
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_a_renewal_can_be_submitted_without_a_member_number(): void
    {
        $response = $this->submitApplication([
            'requestType' => 'renewal',
            'currentMemberNumber' => '',
            'email' => 'numero-inconnu@example.test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.request_type', 'renewal')
            ->assertJsonPath('data.current_member_number', null)
            ->assertJsonPath('data.member_number', null);

        $member = Member::findOrFail($response->json('data.id'));

        $this->assertNull($member->current_member_number);
        $this->assertNull($member->member_number);
        $this->assertStringStartsWith('pending-', $member->user->login);
    }

    public function test_preloaded_members_no_longer_reserve_or_claim_a_number(): void
    {
        $memberNumber = 'UJ25-10003';
        $preloaded = PreloadedMember::create([
            'full_name' => 'KOFFI Jeanne',
            'member_number' => $memberNumber,
            'mapping_status' => 'matched',
        ]);

        $response = $this->submitApplication([
            'requestType' => 'renewal',
            'currentMemberNumber' => $memberNumber,
            'email' => 'ancienne-liste-ignoree@example.test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.current_member_number', $memberNumber)
            ->assertJsonPath('data.member_number', null);

        $preloaded->refresh();

        $this->assertNull($preloaded->member_id);
        $this->assertNull($preloaded->claimed_at);
        $this->assertSame($memberNumber, Member::findOrFail($response->json('data.id'))->current_member_number);
    }

    public function test_a_number_already_confirmed_on_a_member_is_rejected(): void
    {
        $memberNumber = 'UJ25-10001';
        $legacyUser = $this->legacyUser('membre-confirme@example.test', 'legacy-confirmed-login');
        $this->createLegacyMember($legacyUser, null, $memberNumber);

        $this->assertApplicationNumberIsRejected(
            $memberNumber,
            'conflit-member-number@example.test',
        );
    }

    public function test_a_number_provisionally_reserved_by_another_member_is_rejected(): void
    {
        $memberNumber = 'UJ25-10002';
        $legacyUser = $this->legacyUser('reservation-existante@example.test', 'legacy-reservation-login');
        $this->createLegacyMember($legacyUser, $memberNumber, null);

        $this->assertApplicationNumberIsRejected(
            $memberNumber,
            'conflit-current-member-number@example.test',
        );
    }

    public function test_a_number_already_used_as_a_user_login_is_rejected(): void
    {
        $memberNumber = 'UJ25-10004';

        $this->legacyUser('compte-existant@example.test', $memberNumber);

        $this->assertApplicationNumberIsRejected(
            $memberNumber,
            'conflit-user-login@example.test',
        );
    }

    public function test_the_database_constraint_prevents_two_provisional_reservations(): void
    {
        $memberNumber = 'UJ25-10005';
        $firstUser = $this->legacyUser('premiere-reservation@example.test', 'first-reservation-login');
        $secondUser = $this->legacyUser('seconde-reservation@example.test', 'second-reservation-login');

        $this->createLegacyMember($firstUser, $memberNumber, null);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createLegacyMember($secondUser, $memberNumber, null);
    }

    public function test_legacy_number_is_accepted_and_kept_on_admin_approval(): void
    {
        Mail::fake();
        $number = 'UNJCI / 1998 / 042';
        $response = $this->submitApplication([
            'requestType' => 'renewal',
            'currentMemberNumber' => strtolower($number),
            'oldCardRectoFile' => UploadedFile::fake()->create('old-recto.pdf', 10),
            'oldCardVersoFile' => UploadedFile::fake()->create('old-verso.pdf', 10),
        ])->assertCreated()->assertJsonPath('data.current_member_number', $number);
        $member = Member::findOrFail($response->json('data.id'));
        foreach (['photo_file_path', 'press_card_recto', 'old_card_recto_path', 'old_card_verso_path'] as $column) {
            Storage::disk('local')->assertExists($member->$column);
            Storage::disk('public')->assertMissing($member->$column);
        }
        $this->prepareRenewalReview($member);
        $this->putJson("/admin/members/{$member->id}/status", [
            'status' => 'approved', 'verifiedMemberNumber' => $number,
        ])->assertOk()->assertJsonPath('member.member_number', $number);
        $this->assertSame($number, $member->user->fresh()->login);
        $this->submitApplication([
            'requestType' => 'renewal', 'currentMemberNumber' => $number,
            'email' => 'duplicate@example.test', 'pressCardNumber' => '2222JP',
        ])->assertUnprocessable()->assertJsonValidationErrors('currentMemberNumber');
    }

    public function test_rejected_dossier_can_be_reopened_without_losing_payment_or_documents(): void
    {
        $member = $this->createLegacyMember($this->legacyUser('reopen@example.test', 'pending-reopen'), null, null);
        $this->prepareRenewalReview($member);
        $paymentId = $member->payments()->firstOrFail()->id;
        $this->putJson("/admin/members/{$member->id}/status", ['status' => 'rejected'])->assertOk();
        $this->putJson("/admin/members/{$member->id}/status", ['status' => 'pending'])
            ->assertOk()->assertJsonPath('member.membership_stage', 'under_review');
        $this->assertSame('members/photos/legacy.png', $member->fresh()->photo_file_path);
        $this->assertDatabaseHas('payments', ['id' => $paymentId, 'member_id' => $member->id, 'status' => 'approved']);
        $this->assertDatabaseCount('members', 1);
        $this->putJson("/admin/members/{$member->id}/status", ['status' => 'approved', 'generateMemberNumber' => true])
            ->assertOk()->assertJsonPath('member.status', 'approved');
    }

    public function test_missing_legacy_number_can_be_generated_once_without_collisions(): void
    {
        Carbon::setTestNow('2026-10-28 10:00:00');
        $this->legacyUser('reserved@example.test', 'UJ26-00001');
        $member = $this->createLegacyMember($this->legacyUser('generate@example.test', 'pending-generate'), null, null);
        $this->prepareRenewalReview($member);
        $payload = ['status' => 'approved', 'generateMemberNumber' => true];
        $this->putJson("/admin/members/{$member->id}/status", $payload)
            ->assertOk()->assertJsonPath('member.member_number', 'UJ26-00002');
        $expiry = $member->fresh()->membership_expires_at;
        $this->putJson("/admin/members/{$member->id}/status", $payload)
            ->assertOk()->assertJsonPath('member.member_number', 'UJ26-00002');
        $this->assertTrue($expiry->equalTo($member->fresh()->membership_expires_at));
        $this->assertSame('UJ26-00002', $member->user->fresh()->login);
    }

    public function test_member_cannot_reopen_own_rejected_dossier(): void
    {
        $member = $this->createLegacyMember($this->legacyUser('forbidden@example.test', 'pending-forbidden'), null, null);
        $member->update(['status' => 'rejected']);
        Sanctum::actingAs($member->user);
        $this->putJson("/admin/members/{$member->id}/status", ['status' => 'pending'])->assertForbidden();
        $this->assertSame('rejected', $member->fresh()->status);
    }

    public function test_number_generation_still_requires_confirmed_payment(): void
    {
        $member = $this->createLegacyMember($this->legacyUser('unpaid@example.test', 'pending-unpaid'), null, null);
        $this->prepareRenewalReview($member);
        $member->payments()->update(['status' => 'pending']);
        $this->putJson("/admin/members/{$member->id}/status", [
            'status' => 'approved', 'generateMemberNumber' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertNull($member->fresh()->member_number);
    }

    public function test_historical_renewal_already_under_review_can_be_approved_without_a_duplicate_payment_record(): void
    {
        $member = $this->createLegacyMember($this->legacyUser('historical-review@example.test', 'pending-historical-review'), null, null);
        $this->prepareRenewalReview($member);
        $member->payments()->delete();
        $this->assertSame('under_review', $member->fresh()->membership_stage);

        $this->putJson("/admin/members/{$member->id}/status", [
            'status' => 'approved', 'generateMemberNumber' => true,
        ])->assertOk()->assertJsonPath('member.status', 'approved');
        $this->assertNotNull($member->fresh()->member_number);
        $this->assertSame(0, $member->payments()->count());
    }

    public function test_confirmed_renewal_without_legacy_submission_timestamp_can_be_approved(): void
    {
        $member = $this->createLegacyMember($this->legacyUser('missing-review-date@example.test', 'pending-no-review-date'), null, null);
        $this->prepareRenewalReview($member);
        $member->update(['application_submitted_at' => null]);
        $this->assertSame('under_review', $member->fresh()->membership_stage);

        $this->putJson("/admin/members/{$member->id}/status", [
            'status' => 'approved', 'verifiedMemberNumber' => 'ANCIEN / 1998 / 42',
        ])->assertOk()->assertJsonPath('member.member_number', 'ANCIEN / 1998 / 42');
        $this->assertNotNull($member->fresh()->application_submitted_at);
    }

    public function test_legacy_payment_type_does_not_block_a_renewal_already_under_review(): void
    {
        $member = $this->createLegacyMember($this->legacyUser('legacy-payment-type@example.test', 'pending-legacy-type'), null, null);
        $this->prepareRenewalReview($member);
        $member->payments()->update(['payment_type' => 'adhesion']);
        $this->assertSame('under_review', $member->fresh()->membership_stage);

        $this->putJson("/admin/members/{$member->id}/status", [
            'status' => 'approved', 'generateMemberNumber' => true,
        ])->assertOk()->assertJsonPath('member.status', 'approved');
    }

    public function test_unpaid_renewal_cannot_be_approved_or_receive_a_number(): void
    {
        $member = $this->createLegacyMember($this->legacyUser('no-review-no-payment@example.test', 'pending-unpaid-renewal'), null, null);
        $this->prepareRenewalReview($member);
        $member->payments()->delete();
        $member->update(['application_submitted_at' => null]);
        $this->assertSame('awaiting_payment', $member->fresh()->membership_stage);

        $this->putJson("/admin/members/{$member->id}/status", [
            'status' => 'approved', 'generateMemberNumber' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertNull($member->fresh()->member_number);
    }

    public function test_approved_member_can_be_reviewed_again_without_losing_card_payment_or_validity(): void
    {
        Carbon::setTestNow('2026-10-28 12:00:00');
        $number = 'UJ26-00991';
        $member = $this->createLegacyMember($this->legacyUser('review-again@example.test', $number), $number, $number);
        $this->prepareRenewalReview($member);
        $member->update([
            'status' => 'approved', 'approved_at' => now(),
            'membership_expires_at' => '2027-06-30', 'qr_token' => 'preserved-qr-token',
        ]);
        // Une autre tentative de renouvellement ne doit pas masquer le réexamen.
        $member->payments()->create([
            'amount' => 5000, 'payment_phone' => 'Wave', 'transaction_id' => 'RETRY-review',
            'payment_type' => 'renewal', 'status' => 'pending',
        ]);

        for ($review = 0; $review < 2; $review++) {
            $this->putJson("/admin/members/{$member->id}/status", ['status' => 'pending'])
                ->assertOk()->assertJsonPath('member.membership_stage', 'under_review')
                ->assertJsonPath('member.card_is_valid', false)
                ->assertJsonPath('member.member_number', $number)
                ->assertJsonPath('member.qr_token', 'preserved-qr-token');
            $this->assertSame('2027-06-30', $member->fresh()->membership_expires_at->format('Y-m-d'));

            $this->putJson("/admin/members/{$member->id}/status", ['status' => 'approved'])
                ->assertOk()->assertJsonPath('member.status', 'approved')
                ->assertJsonPath('member.card_is_valid', true)
                ->assertJsonPath('member.member_number', $number);
            $this->assertSame('2027-06-30', $member->fresh()->membership_expires_at->format('Y-m-d'));
        }
        $this->assertSame('members/photos/legacy.png', $member->fresh()->photo_file_path);
        $this->assertSame($number, $member->user->fresh()->login);
        $this->assertSame(['approved', 'pending'], $member->payments()->orderBy('id')->pluck('status')->all());
        $this->assertDatabaseCount('members', 1);
    }

    public function test_reviewing_a_historical_approved_member_does_not_reactivate_an_expired_card(): void
    {
        Carbon::setTestNow('2026-10-28 12:00:00');
        $number = 'UJ25-00881';
        $member = $this->createLegacyMember($this->legacyUser('expired-review@example.test', $number), $number, $number);
        $this->prepareRenewalReview($member);
        $member->payments()->delete();
        $member->update([
            'status' => 'approved', 'application_submitted_at' => null, 'approved_at' => null,
            'membership_expires_at' => '2026-06-30', 'qr_token' => 'expired-card-qr',
        ]);
        $this->putJson("/admin/members/{$member->id}/status", ['status' => 'pending'])
            ->assertOk()->assertJsonPath('member.membership_stage', 'under_review');
        $this->assertNotNull($member->fresh()->application_submitted_at);
        $this->putJson("/admin/members/{$member->id}/status", ['status' => 'approved'])
            ->assertOk()->assertJsonPath('member.card_is_valid', false);
        $this->assertSame('2026-06-30', $member->fresh()->membership_expires_at->format('Y-m-d'));
        $this->assertSame(0, $member->payments()->count());
    }

    public function test_member_cannot_put_own_approved_dossier_under_review(): void
    {
        $number = 'UJ26-00992';
        $member = $this->createLegacyMember($this->legacyUser('forbidden-review@example.test', $number), $number, $number);
        $member->update(['status' => 'approved']);
        Sanctum::actingAs($member->user);
        $this->putJson("/admin/members/{$member->id}/status", ['status' => 'pending'])->assertForbidden();
        $this->assertSame('approved', $member->fresh()->status);
    }

    private function prepareRenewalReview(Member $member): void
    {
        Mail::fake();
        $member->update(['application_submitted_at' => now()]);
        $member->payments()->create([
            'amount' => 5000, 'payment_phone' => 'Wave', 'transaction_id' => 'TEST-'.$member->id,
            'payment_type' => 'renewal', 'status' => 'approved',
        ]);
        $admin = $this->legacyUser('reviewer@example.test', 'reviewer');
        $admin->update(['role' => 'admin']);
        Sanctum::actingAs($admin);
    }

    private function assertApplicationNumberIsRejected(string $memberNumber, string $email): void
    {
        $this->submitApplication([
            'requestType' => 'renewal',
            'currentMemberNumber' => $memberNumber,
            'email' => $email,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currentMemberNumber');

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    private function submitApplication(array $overrides = []): TestResponse
    {
        if (($overrides['requestType'] ?? '') === 'renewal') {
            $overrides += [
                'oldCardRectoFile' => UploadedFile::fake()->create('old-recto.pdf', 10, 'application/pdf'),
                'oldCardVersoFile' => UploadedFile::fake()->create('old-verso.pdf', 10, 'application/pdf'),
            ];
        }
        return $this->withHeader('Accept', 'application/json')
            ->post('/members/apply', $this->validApplicationData($overrides));
    }

    private function validApplicationData(array $overrides = []): array
    {
        return array_merge([
            'lastName' => 'KOUASSI',
            'firstName' => 'Awa',
            'birthDate' => '01/01/1990',
            'birthPlace' => 'Abidjan',
            'phone' => '0102030405',
            'email' => 'nouveau@example.test',
            'requestType' => 'adhesion',
            'professionalStatus' => 'Journaliste mensualisé (CDI/CDD)',
            'employers' => 'GROUPE RTI',
            'mediaName' => 'RTI1',
            'mediaType' => 'Numérique',
            'functionTitle' => 'Reporter',
            'pressCardNumber' => '1234JP',
            'pressCardExpiry' => '2030-12-31',
            'password' => 'password123',
            'declarationAccepted' => true,
            'privacyAccepted' => true,
            'pressCardRectoFile' => UploadedFile::fake()->create('recto.pdf', 10, 'application/pdf'),
            'pressCardVersoFile' => UploadedFile::fake()->create('verso.pdf', 10, 'application/pdf'),
            'photoFile' => UploadedFile::fake()->create('photo.png', 10, 'image/png'),
        ], $overrides);
    }

    private function legacyUser(string $email, string $login): User
    {
        return User::create([
            'name' => 'Compte historique',
            'email' => $email,
            'login' => $login,
            'password' => Hash::make('password123'),
            'role' => 'member',
        ]);
    }

    private function createLegacyMember(
        User $user,
        ?string $currentMemberNumber,
        ?string $memberNumber,
    ): Member {
        return Member::create([
            'user_id' => $user->id,
            'last_name' => 'YAO',
            'first_name' => 'Michel',
            'birth_date' => '1980-01-01',
            'birth_place' => 'Bouaké',
            'phone' => '0700000000',
            'personal_email' => $user->email,
            'request_type' => 'renewal',
            'current_member_number' => $currentMemberNumber,
            'member_number' => $memberNumber,
            'professional_status' => 'Journaliste mensualisé (CDI/CDD)',
            'employers' => 'Ancienne entreprise',
            'media_name' => 'Ancien média',
            'media_type' => 'Écrit',
            'function_title' => 'Reporter',
            'press_card_number' => '9999JP',
            'press_card_expiry' => '2030-12-31',
            'photo_file_path' => 'members/photos/legacy.png',
            'declaration_accepted' => true,
            'privacy_accepted' => true,
        ]);
    }
}
