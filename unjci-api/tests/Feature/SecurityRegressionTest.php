<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use App\Mail\ApplicationUnderReviewMail;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function member(): Member
    {
        $user = User::factory()->create(['login' => 'member-'.uniqid(), 'role' => 'member']);
        return Member::create([
            'user_id' => $user->id, 'last_name' => 'KOUASSI', 'first_name' => 'Awa',
            'birth_date' => '1990-01-01', 'birth_place' => 'Abidjan', 'phone' => '0102030405',
            'personal_email' => $user->email, 'request_type' => 'adhesion', 'professional_status' => 'Journaliste',
            'employers' => 'RTI', 'media_name' => 'RTI1', 'media_type' => 'Écrit', 'function_title' => 'Reporter',
            'press_card_number' => uniqid().'JP', 'press_card_expiry' => '2030-12-31',
            'photo_file_path' => 'members/photos/'.$user->id.'.png',
            'press_card_recto' => 'members/press_cards_recto/'.$user->id.'.pdf',
            'declaration_accepted' => true, 'privacy_accepted' => true,
        ]);
    }

    private function event(Member $member): array
    {
        config(['services.wave.webhook_secret' => 'test-webhook-secret']);
        $payment = $member->payments()->create([
            'amount' => 10000, 'payment_phone' => 'Wave', 'transaction_id' => 'INIT-test',
            'payment_type' => 'adhesion', 'checkout_session_id' => 'cos-test', 'status' => 'pending',
        ]);
        return ['type' => 'checkout.session.completed', 'data' => [
            'client_reference' => (string) $payment->id, 'id' => 'cos-test', 'transaction_id' => 'TX-test',
            'amount' => '10000', 'currency' => 'XOF', 'checkout_status' => 'complete', 'payment_status' => 'succeeded',
        ]];
    }

    private function signed(array $event, ?int $timestamp = null)
    {
        $body = json_encode($event);
        $timestamp ??= now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.$body, 'test-webhook-secret');
        return $this->call('POST', '/webhooks/wave', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_WAVE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $body);
    }

    public function test_wave_rejects_unsigned_and_stale_messages(): void
    {
        $member = $this->member();
        $event = $this->event($member);
        $this->postJson('/webhooks/wave', $event)->assertUnauthorized();
        $this->signed($event, now()->timestamp - 301)->assertUnauthorized();
        $this->signed($event, now()->timestamp + 31)->assertUnauthorized();
        $this->assertSame('pending', $member->payments()->first()->status);
    }

    public function test_wave_validates_payment_once_and_requires_matching_session_amount_and_currency(): void
    {
        Mail::fake();
        $member = $this->member();
        $event = $this->event($member);
        foreach (['id' => 'cos-other', 'amount' => '5000', 'currency' => 'EUR', 'payment_status' => 'failed'] as $key => $value) {
            $invalid = $event;
            $invalid['data'][$key] = $value;
            $this->signed($invalid)->assertUnprocessable();
            $this->assertSame('pending', $member->payments()->first()->status);
        }
        $this->signed($event)->assertOk();
        $submittedAt = $member->fresh()->application_submitted_at;
        $this->signed($event)->assertOk();
        $this->assertSame('approved', $member->payments()->first()->status);
        $this->assertTrue($submittedAt->equalTo($member->fresh()->application_submitted_at));
        $this->assertSame('pending', $member->fresh()->status);
        Mail::assertSent(ApplicationUnderReviewMail::class, 1);
    }

    public function test_wave_renewal_extends_validity_once_and_never_reopens_a_rejected_member(): void
    {
        $member = $this->member();
        $member->update(['status' => 'approved', 'member_number' => 'UJ26-00001', 'qr_token' => 'renewal-qr', 'membership_expires_at' => today()->addYear()]);
        $previousExpiry = $member->fresh()->membership_expires_at;
        $event = $this->event($member);
        $member->payments()->update(['payment_type' => 'renewal']);
        $this->signed($event)->assertOk();
        $this->signed($event)->assertOk();
        $this->assertTrue($member->fresh()->membership_expires_at->equalTo($previousExpiry->copy()->addYear()));
        $member->update(['status' => 'rejected']);
        $member->payments()->delete();
        $event = $this->event($member);
        $this->signed($event)->assertOk();
        $this->assertSame('rejected', $member->fresh()->status);
    }

    public function test_checkout_signs_exact_request_body_and_returns_to_member_area(): void
    {
        config(['services.wave.api_key' => 'test-key', 'services.wave.signing_secret' => 'test-signing']);
        Http::preventStrayRequests();
        Http::fake(['api.wave.com/*' => Http::response(['id' => 'cos-test', 'wave_launch_url' => 'https://pay.wave.com/test'])]);
        Sanctum::actingAs($this->member()->user);
        $this->postJson('/member/payment/wave/initiate', ['paymentType' => 'adhesion'])->assertOk();
        Http::assertSent(function ($request) {
            $header = $request->header('Wave-Signature')[0];
            preg_match('/t=(\d+),v1=(.*)/', $header, $parts);
            return hash_equals(hash_hmac('sha256', $parts[1].$request->body(), 'test-signing'), $parts[2])
                && str_contains($request['success_url'], '/espace-membre?') && $request['amount'] === '10000';
        });
    }

    public function test_secondary_admin_gets_only_authorized_data(): void
    {
        $member = $this->member();
        $this->event($member);
        $user = User::factory()->create(['login' => 'limited', 'role' => 'media_admin', 'permissions' => ['manage_media']]);
        Sanctum::actingAs($user);
        $this->getJson('/admin/dashboard')->assertOk()->assertJsonPath('members', [])->assertJsonPath('payments', []);
        $user->update(['permissions' => ['view_payment_history']]);
        $this->getJson('/admin/dashboard')->assertOk()->assertJsonPath('members', [])
            ->assertJsonMissingPath('payments.0.member.personal_email')->assertJsonCount(1, 'payments');
    }

    public function test_files_require_owner_or_explicit_permission_for_both_storage_disks(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $member = $this->member();
        Storage::disk('local')->put($member->press_card_recto, 'private-pdf');
        Storage::disk('public')->put($member->photo_file_path, 'legacy-photo');
        $this->getJson('/storage/'.$member->press_card_recto)->assertUnauthorized();
        Sanctum::actingAs($this->member()->user);
        $this->getJson('/storage/'.$member->press_card_recto)->assertForbidden();
        Sanctum::actingAs($member->user);
        $this->get('/storage/'.$member->press_card_recto)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/storage/'.$member->photo_file_path)->assertOk();
        Sanctum::actingAs(User::factory()->create(['login' => 'scanner', 'role' => 'scanner']));
        $this->get('/storage/'.$member->press_card_recto)->assertForbidden();
        $this->get('/storage/'.$member->photo_file_path)->assertOk();
    }

    public function test_card_verification_checks_expiration_and_approval_and_limits_personal_data(): void
    {
        $member = $this->member();
        $member->update(['qr_token' => 'test-qr', 'member_number' => 'UJ26-00001', 'status' => 'approved', 'membership_expires_at' => today()]);
        Sanctum::actingAs(User::factory()->create(['login' => 'scanner', 'role' => 'scanner']));
        $this->getJson('/verify-card/test-qr')->assertOk()->assertJsonMissingPath('member.personal_email')
            ->assertJsonMissingPath('member.press_card_recto');
        $member->update(['membership_expires_at' => today()->subDay()]);
        $this->getJson('/verify-card/test-qr')->assertUnprocessable();
        $member->update(['membership_expires_at' => today()->addYear(), 'status' => 'rejected']);
        $this->getJson('/verify-card/test-qr')->assertUnprocessable();
    }

    public function test_public_cache_clearing_is_removed_and_contact_requires_consent(): void
    {
        $this->getJson('/clear-cache')->assertNotFound();
        $this->postJson('/contact', [
            'fullName' => 'Awa Kouassi', 'email' => 'awa@example.test', 'subject' => 'Question',
            'message' => 'Une demande de renseignement.', 'consent' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('consent');
    }

    public function test_logout_revokes_the_current_access_token(): void
    {
        $member = $this->member();
        $login = $this->postJson('/login', ['login' => $member->user->email, 'password' => 'password'])
            ->assertOk();
        $token = $login->json('token');
        $this->assertNotNull($member->user->tokens()->firstOrFail()->expires_at);
        $this->withToken($token)->postJson('/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
