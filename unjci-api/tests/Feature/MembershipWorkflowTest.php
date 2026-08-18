<?php

namespace Tests\Feature;

use App\Mail\ApplicationUnderReviewMail;
use App\Mail\MembershipApprovedMail;
use App\Models\Member;
use App\Models\PreloadedMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Mail::fake();
        Carbon::setTestNow('2026-08-11 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_new_member_logs_in_by_email_and_receives_number_only_after_final_approval(): void
    {
        // L'ancien registre importé est obsolète et ne réserve plus les numéros
        // générés pour les nouvelles adhésions.
        PreloadedMember::create([
            'full_name' => 'Ancienne ligne importée',
            'member_number' => 'UJ26-00001',
            'mapping_status' => 'unmatched',
        ]);

        $response = $this->post('/members/apply', $this->applicationData())
            ->assertCreated()
            ->assertJsonPath('data.request_type', 'adhesion')
            ->assertJsonPath('data.member_number', null)
            ->assertJsonPath('data.current_member_number', null)
            ->assertJsonPath('login_identifier', 'awa@example.test');

        $member = Member::findOrFail($response->json('data.id'));
        $this->assertSame('awaiting_payment', $member->membership_stage);
        $this->assertStringStartsWith('pending-', $member->user->login);

        $this->postJson('/login', [
            'login' => 'awa@example.test',
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['token']);

        Sanctum::actingAs($member->user);
        $paymentId = $this->post('/member/payment', [
            'paymentPhone' => '+2250700000000',
            'transactionId' => 'TX-ADH-001',
            'paymentType' => 'adhesion',
        ])->assertOk()->assertJsonPath('payment.status', 'pending')->json('payment.id');

        $this->assertSame('payment_pending', $member->fresh()->membership_stage);

        Sanctum::actingAs($this->admin());
        $this->putJson("/admin/payments/{$paymentId}/validate", ['status' => 'approved'])
            ->assertOk();

        $member->refresh();
        $this->assertSame('pending', $member->status);
        $this->assertNull($member->member_number);
        $this->assertNotNull($member->application_submitted_at);
        $this->assertSame('under_review', $member->membership_stage);
        Mail::assertSent(ApplicationUnderReviewMail::class, fn ($mail) => $mail->hasTo($member->personal_email));
        Mail::assertNotSent(MembershipApprovedMail::class);

        $this->putJson("/admin/members/{$member->id}/status", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('member.status', 'approved')
            ->assertJsonPath('member.member_number', 'UJ26-00001');

        $member->refresh();
        $this->assertSame('UJ26-00001', $member->member_number);
        $this->assertSame('UJ26-00001', $member->current_member_number);
        $this->assertSame('UJ26-00001', $member->user->login);
        $this->assertNotNull($member->qr_token);
        $this->assertSame('2027-08-11', $member->membership_expires_at->format('Y-m-d'));
        $this->assertSame('approved', $member->membership_stage);
        Mail::assertSent(MembershipApprovedMail::class, function ($mail) use ($member) {
            return $mail->hasTo($member->personal_email)
                && $mail->member->member_number === 'UJ26-00001';
        });

        // Une seconde requête identique reste idempotente.
        $this->putJson("/admin/members/{$member->id}/status", ['status' => 'approved'])->assertOk();
        Mail::assertSent(MembershipApprovedMail::class, 1);

        $this->postJson('/login', [
            'login' => 'UJ26-00001',
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_admin_cannot_approve_a_new_application_before_payment_confirmation(): void
    {
        $memberId = $this->post('/members/apply', $this->applicationData([
            'email' => 'sans-paiement@example.test',
            'pressCardNumber' => '2222JP',
        ]))->assertCreated()->json('data.id');

        Sanctum::actingAs($this->admin());
        $this->putJson("/admin/members/{$memberId}/status", ['status' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertNull(Member::findOrFail($memberId)->member_number);
        Mail::assertNothingSent();
    }

    public function test_renewal_with_a_reported_number_costs_5000_and_waits_for_admin_verification(): void
    {
        $memberNumber = 'UJ24-00123';
        $response = $this->post('/members/apply', $this->applicationData([
            'email' => 'jeanne@example.test',
            'pressCardNumber' => '3333JP',
            'requestType' => 'renewal',
            'currentMemberNumber' => $memberNumber,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.request_type', 'renewal')
            ->assertJsonPath('data.current_member_number', $memberNumber)
            ->assertJsonPath('data.member_number', null);

        $member = Member::findOrFail($response->json('data.id'));
        $pendingLogin = $member->user->login;
        $this->assertStringStartsWith('pending-', $pendingLogin);
        $member->update(['membership_expires_at' => '2027-01-31']);

        Sanctum::actingAs($member->user);
        $paymentId = $this->post('/member/payment', [
            'paymentPhone' => '+2250500000000',
            'transactionId' => 'TX-REN-001',
            'paymentType' => 'renewal',
        ])->assertOk()
            ->assertJsonPath('payment.amount', 5000)
            ->assertJsonPath('payment.previous_member_number', $memberNumber)
            ->assertJsonPath('payment.status', 'pending')
            ->json('payment.id');

        Sanctum::actingAs($this->admin());
        $this->putJson("/admin/payments/{$paymentId}/validate", ['status' => 'approved'])
            ->assertOk();

        $member->refresh();
        $this->assertSame('pending', $member->status);
        $this->assertSame('under_review', $member->membership_stage);
        $this->assertNotNull($member->application_submitted_at);
        $this->assertNull($member->member_number);
        $this->assertSame($memberNumber, $member->current_member_number);
        $this->assertSame($pendingLogin, $member->user->login);
        $this->assertSame('2027-01-31', $member->membership_expires_at->format('Y-m-d'));
        Mail::assertSent(ApplicationUnderReviewMail::class, fn ($mail) => $mail->hasTo($member->personal_email));
        Mail::assertNotSent(MembershipApprovedMail::class);

        $this->putJson("/admin/members/{$member->id}/status", ['status' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verifiedMemberNumber');

        $member->refresh();
        $this->assertSame('pending', $member->status);
        $this->assertNull($member->member_number);
        $this->assertSame($pendingLogin, $member->user->login);

        $this->putJson("/admin/members/{$member->id}/status", [
            'status' => 'approved',
            'verifiedMemberNumber' => $memberNumber,
        ])
            ->assertOk()
            ->assertJsonPath('member.status', 'approved')
            ->assertJsonPath('member.member_number', $memberNumber)
            ->assertJsonPath('member.current_member_number', $memberNumber);

        $member->refresh();
        $this->assertSame($memberNumber, $member->member_number);
        $this->assertSame($memberNumber, $member->current_member_number);
        $this->assertSame($memberNumber, $member->user->login);
        $this->assertSame('2028-01-31', $member->membership_expires_at->format('Y-m-d'));
        $this->assertSame('approved', $member->membership_stage);
        Mail::assertSent(MembershipApprovedMail::class, fn ($mail) => $mail->hasTo($member->personal_email));
    }

    public function test_renewal_without_a_reported_number_requires_the_admin_to_enter_one(): void
    {
        $response = $this->post('/members/apply', $this->applicationData([
            'email' => 'numero-a-verifier@example.test',
            'pressCardNumber' => '4444JP',
            'requestType' => 'renewal',
            'currentMemberNumber' => '',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.request_type', 'renewal')
            ->assertJsonPath('data.current_member_number', null)
            ->assertJsonPath('data.member_number', null);

        $member = Member::findOrFail($response->json('data.id'));
        $pendingLogin = $member->user->login;

        Sanctum::actingAs($member->user);
        $paymentId = $this->post('/member/payment', [
            'paymentPhone' => '+2250100000000',
            'transactionId' => 'TX-REN-NUMBER-UNKNOWN',
            'paymentType' => 'renewal',
        ])->assertOk()
            ->assertJsonPath('payment.amount', 5000)
            ->assertJsonPath('payment.previous_member_number', null)
            ->json('payment.id');

        Sanctum::actingAs($this->admin());
        $this->putJson("/admin/payments/{$paymentId}/validate", ['status' => 'approved'])
            ->assertOk();

        $this->putJson("/admin/members/{$member->id}/status", ['status' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verifiedMemberNumber');

        $member->refresh();
        $this->assertSame('pending', $member->status);
        $this->assertNull($member->member_number);
        $this->assertNull($member->current_member_number);
        $this->assertSame($pendingLogin, $member->user->login);

        $verifiedMemberNumber = 'UJ24-00456';
        $this->putJson("/admin/members/{$member->id}/status", [
            'status' => 'approved',
            'verifiedMemberNumber' => '  uj24-00456  ',
        ])
            ->assertOk()
            ->assertJsonPath('member.member_number', $verifiedMemberNumber);

        $member->refresh();
        $this->assertSame($verifiedMemberNumber, $member->member_number);
        $this->assertSame($verifiedMemberNumber, $member->current_member_number);
        $this->assertSame($verifiedMemberNumber, $member->user->login);
        $this->assertSame('approved', $member->status);
    }

    public function test_admin_approval_rechecks_a_number_that_became_conflicting(): void
    {
        $memberNumber = 'UJ24-00999';
        $response = $this->post('/members/apply', $this->applicationData([
            'email' => 'conflit-tardif@example.test',
            'pressCardNumber' => '5555JP',
            'requestType' => 'renewal',
            'currentMemberNumber' => $memberNumber,
        ]))->assertCreated();

        $member = Member::findOrFail($response->json('data.id'));
        $pendingLogin = $member->user->login;

        Sanctum::actingAs($member->user);
        $paymentId = $this->post('/member/payment', [
            'paymentPhone' => '+2250700000001',
            'transactionId' => 'TX-REN-LATE-CONFLICT',
            'paymentType' => 'renewal',
        ])->assertOk()->json('payment.id');

        Sanctum::actingAs($this->admin());
        $this->putJson("/admin/payments/{$paymentId}/validate", ['status' => 'approved'])
            ->assertOk();

        User::create([
            'name' => 'Compte concurrent',
            'email' => 'compte-concurrent@example.test',
            'login' => $memberNumber,
            'password' => Hash::make('password123'),
            'role' => 'member',
        ]);

        $this->putJson("/admin/members/{$member->id}/status", [
            'status' => 'approved',
            'verifiedMemberNumber' => $memberNumber,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('verifiedMemberNumber');

        $member->refresh();
        $this->assertSame('pending', $member->status);
        $this->assertNull($member->member_number);
        $this->assertSame($memberNumber, $member->current_member_number);
        $this->assertSame($pendingLogin, $member->user->login);
        $this->assertNull($member->approved_at);
        Mail::assertNotSent(MembershipApprovedMail::class);
    }

    public function test_rejecting_a_provisional_renewal_releases_its_reported_number(): void
    {
        $memberNumber = 'UJ24-00888';
        $response = $this->post('/members/apply', $this->applicationData([
            'email' => 'dossier-a-rejeter@example.test',
            'pressCardNumber' => '6666JP',
            'requestType' => 'renewal',
            'currentMemberNumber' => $memberNumber,
        ]))->assertCreated();

        $rejectedMember = Member::findOrFail($response->json('data.id'));
        $pendingLogin = $rejectedMember->user->login;

        Sanctum::actingAs($rejectedMember->user);
        $paymentId = $this->post('/member/payment', [
            'paymentPhone' => '+2250700000002',
            'transactionId' => 'TX-REN-REJECTED',
            'paymentType' => 'renewal',
        ])->assertOk()->json('payment.id');

        Sanctum::actingAs($this->admin());
        $this->putJson("/admin/payments/{$paymentId}/validate", ['status' => 'approved'])
            ->assertOk();

        $this->putJson("/admin/members/{$rejectedMember->id}/status", ['status' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('member.status', 'rejected')
            ->assertJsonPath('member.current_member_number', null)
            ->assertJsonPath('member.member_number', null);

        $rejectedMember->refresh();
        $this->assertNull($rejectedMember->current_member_number);
        $this->assertNull($rejectedMember->member_number);
        $this->assertSame($pendingLogin, $rejectedMember->user->login);

        $secondResponse = $this->post('/members/apply', $this->applicationData([
            'email' => 'nouveau-dossier-meme-numero@example.test',
            'pressCardNumber' => '7777JP',
            'requestType' => 'renewal',
            'currentMemberNumber' => $memberNumber,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.current_member_number', $memberNumber)
            ->assertJsonPath('data.member_number', null);

        $newMember = Member::findOrFail($secondResponse->json('data.id'));
        $this->assertSame($memberNumber, $newMember->current_member_number);
        $this->assertStringStartsWith('pending-', $newMember->user->login);
    }

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'Admin UNJCI',
                'login' => 'admin-workflow',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
        );
    }

    private function applicationData(array $overrides = []): array
    {
        return array_merge([
            'lastName' => 'KOUASSI',
            'firstName' => 'Awa',
            'birthDate' => '01/01/1990',
            'birthPlace' => 'Abidjan',
            'phone' => '0102030405',
            'email' => 'awa@example.test',
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
}
