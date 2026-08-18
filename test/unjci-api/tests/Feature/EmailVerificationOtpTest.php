<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationOtpMail;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_otp_is_sent_and_only_its_hash_is_stored(): void
    {
        Mail::fake();
        $user = $this->pendingUser();

        $this->postJson('/members/email-verification/send', ['email' => $user->email])
            ->assertOk()
            ->assertJsonMissingPath('code');

        Mail::assertSent(EmailVerificationOtpMail::class, function ($mail) use ($user) {
            $storedHash = DB::table('email_verification_otps')->where('email', $user->email)->value('code_hash');

            return $mail->hasTo($user->email)
                && $mail->code !== $storedHash
                && Hash::check($mail->code, $storedHash);
        });
    }

    public function test_a_valid_otp_verifies_email_and_cannot_be_replayed(): void
    {
        $user = $this->pendingUser('123456');

        $this->postJson('/members/email-verification/verify', ['email' => $user->email, 'code' => '123456'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseMissing('email_verification_otps', ['email' => $user->email]);

        $this->postJson('/members/email-verification/verify', ['email' => $user->email, 'code' => '123456'])
            ->assertStatus(422);
    }

    public function test_an_invalid_or_expired_otp_is_rejected(): void
    {
        $user = $this->pendingUser('123456');

        $this->postJson('/members/email-verification/verify', ['email' => $user->email, 'code' => '654321'])
            ->assertStatus(422);
        $this->assertDatabaseHas('email_verification_otps', ['email' => $user->email, 'attempts' => 1]);

        DB::table('email_verification_otps')->where('email', $user->email)->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/members/email-verification/verify', ['email' => $user->email, 'code' => '123456'])
            ->assertStatus(422);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_pending_new_member_is_blocked_but_historical_account_is_not(): void
    {
        $pending = $this->pendingUser();

        $this->postJson('/login', ['login' => $pending->login, 'password' => 'password123'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'email_verification_required')
            ->assertJsonMissingPath('token');

        $historical = $this->createUser('historical@example.test', 'UJ25-00002', '0002JP');
        $this->postJson('/login', ['login' => $historical->login, 'password' => 'password123'])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    private function pendingUser(string $code = 'verification-pending'): User
    {
        $user = $this->createUser('pending@example.test', 'UJ25-00001', '0001JP');
        DB::table('email_verification_otps')->insert([
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'last_sent_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function createUser(string $email, string $memberNumber, string $pressCardNumber): User
    {
        $user = User::create([
            'name' => 'Membre Test',
            'email' => $email,
            'login' => $memberNumber,
            'password' => Hash::make('password123'),
            'role' => 'member',
        ]);

        Member::create([
            'user_id' => $user->id,
            'last_name' => 'TEST',
            'first_name' => 'Membre',
            'birth_date' => '1990-01-01',
            'birth_place' => 'Abidjan',
            'phone' => '0102030405',
            'personal_email' => $email,
            'request_type' => 'adhesion',
            'current_member_number' => $memberNumber,
            'member_number' => $memberNumber,
            'professional_status' => 'Journaliste',
            'employers' => 'Entreprise Test',
            'media_name' => 'Média Test',
            'media_type' => 'Numérique',
            'function_title' => 'Rédacteur',
            'press_card_number' => $pressCardNumber,
            'press_card_expiry' => '2030-12-31',
            'press_card_recto' => 'tests/recto.jpg',
            'press_card_verso' => 'tests/verso.jpg',
            'photo_file_path' => 'tests/photo.jpg',
            'declaration_accepted' => true,
            'privacy_accepted' => true,
        ]);

        return $user;
    }
}
