<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MemberInputValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_absurd_identity_values_are_rejected_with_french_messages(): void
    {
        $this->submitApplication([
            'lastName' => ' a ',
            'firstName' => 'Awa 2',
            'alias' => ' x ',
            'birthPlace' => '123 A',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.lastName.0', 'Le nom doit contenir au moins 2 lettres.')
            ->assertJsonPath('errors.firstName.0', 'Le prénom ne peut contenir que des lettres, espaces, apostrophes et tirets.')
            ->assertJsonPath('errors.alias.0', 'Le pseudonyme doit contenir au moins 2 lettres.')
            ->assertJsonPath('errors.birthPlace.0', 'Le lieu de naissance doit contenir au moins 2 lettres.');
    }

    public function test_phone_number_rejects_letters(): void
    {
        $this->submitApplication(['phone' => '+225 07 AB 09 10 11'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'Le numéro de téléphone contient des caractères non autorisés.');
    }

    public function test_phone_number_requires_between_eight_and_fifteen_digits(): void
    {
        foreach (['12 34 56 7', '+225 01 23 45 67 89 01 23'] as $phone) {
            $this->submitApplication(['phone' => $phone])
                ->assertUnprocessable()
                ->assertJsonPath('errors.phone.0', 'Le numéro de téléphone doit contenir entre 8 et 15 chiffres.');
        }
    }

    public function test_future_birth_date_is_rejected(): void
    {
        $this->submitApplication(['birthDate' => now()->addDay()->format('d/m/Y')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.birthDate.0', 'La date de naissance ne peut pas être dans le futur.');
    }

    public function test_email_requires_a_complete_domain_name(): void
    {
        $this->submitApplication(['email' => 'test@tes'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'L’adresse e-mail doit être au format nom@domaine.com.');

        $this->submitApplication(['email' => 'test@tes.com'])
            ->assertCreated();
    }

    public function test_common_ivorian_formats_and_unicode_names_are_accepted_and_normalized(): void
    {
        $formats = [
            '(+225) 07 08-09-10-11',
            '+225 01 23 45 67 89',
        ];

        foreach ($formats as $index => $phone) {
            $sequence = $index + 1;
            $expectedAlias = $index === 0 ? "L'Étoile" : null;
            $expectedPostalAddress = $index === 0 ? 'Cocody Angré' : null;
            $response = $this->submitApplication([
                'lastName' => '  N’GUESSAN   KOUA-KOFFI  ',
                'firstName' => "  Anne-Marie   d'Arc  ",
                'alias' => $index === 0 ? "  L'Étoile  " : '   ',
                'birthDate' => '  01/01/1990  ',
                'birthPlace' => '  Abidjan   2  ',
                'phone' => "  {$phone}  ",
                'postalAddress' => $index === 0 ? '  Cocody   Angré  ' : '   ',
                'email' => "  format-ivoirien-{$sequence}@example.test  ",
                'requestType' => '  adhesion  ',
                'professionalStatus' => '  Journaliste   professionnel  ',
                'employers' => '  GROUPE   RTI  ',
                'mediaName' => '  RTI1  ',
                'functionTitle' => '  Grand   reporter  ',
                'pressCardNumber' => sprintf('%04dJP', 2000 + $sequence),
            ])->assertCreated();

            $response->assertJsonPath('data.last_name', 'N’GUESSAN KOUA-KOFFI')
                ->assertJsonPath('data.first_name', "Anne-Marie d'Arc")
                ->assertJsonPath('data.alias', $expectedAlias)
                ->assertJsonPath('data.postal_address', $expectedPostalAddress)
                ->assertJsonPath('data.personal_email', "format-ivoirien-{$sequence}@example.test")
                ->assertJsonPath('data.request_type', 'adhesion')
                ->assertJsonPath('data.birth_place', 'Abidjan 2')
                ->assertJsonPath('data.phone', $phone)
                ->assertJsonPath('data.professional_status', 'Journaliste professionnel')
                ->assertJsonPath('data.employers', 'GROUPE RTI')
                ->assertJsonPath('data.media_name', 'RTI1')
                ->assertJsonPath('data.function_title', 'Grand reporter');
        }
    }

    public function test_database_backed_text_fields_are_limited_before_insertion(): void
    {
        $tooLong = str_repeat('x', 256);

        $this->submitApplication([
            'requestType' => $tooLong,
            'professionalStatus' => $tooLong,
            'employers' => $tooLong,
            'functionTitle' => $tooLong,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'requestType',
                'professionalStatus',
                'employers',
                'functionTitle',
            ]);
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
            'email' => 'validation-membre@example.test',
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
