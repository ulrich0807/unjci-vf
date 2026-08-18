<?php

namespace Tests\Feature;

use App\Mail\TemporaryPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_email_receives_a_clear_response(): void
    {
        Mail::fake();

        $this->postJson('/forgot-password', ['email' => 'absent@example.com'])
            ->assertNotFound()
            ->assertJson([
                'code' => 'email_not_found',
                'message' => 'Aucun compte n’est associé à cette adresse e-mail. Vérifiez l’adresse saisie ou contactez l’UNJCI.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_a_temporary_password_is_sent_for_an_existing_account(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'login' => 'UJ26-00001',
            'email' => 'membre@example.com',
            'password' => Hash::make('ancien-mot-de-passe'),
        ]);

        $this->postJson('/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJson([
                'message' => 'Un mot de passe temporaire a été envoyé à cette adresse. Consultez votre boîte de réception et vos courriers indésirables.',
            ]);

        Mail::assertSent(TemporaryPasswordMail::class, function (TemporaryPasswordMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && Hash::check($mail->temporaryPassword, $user->fresh()->password);
        });
    }

    public function test_an_invalid_email_receives_a_clear_validation_message(): void
    {
        $this->postJson('/forgot-password', ['email' => 'adresse-invalide'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Saisissez une adresse e-mail valide.');
    }
}
