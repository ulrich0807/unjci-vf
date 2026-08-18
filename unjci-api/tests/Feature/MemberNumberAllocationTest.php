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
