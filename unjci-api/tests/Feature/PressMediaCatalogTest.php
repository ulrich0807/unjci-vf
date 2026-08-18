<?php

namespace Tests\Feature;

use App\Models\PressCompany;
use App\Models\PressMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PressMediaCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_catalog_is_imported_and_publicly_available_in_the_expected_format(): void
    {
        $this->assertDatabaseCount('press_companies', 171);
        $this->assertDatabaseCount('press_media', 209);

        $rti = PressCompany::where('name', 'GROUPE RTI')->firstOrFail();
        $this->assertSame(
            ['RTI BOUAKE', 'RTI1', 'RTI2'],
            $rti->media()->pluck('name')->all(),
        );

        $response = $this->getJson('/press-media')
            ->assertOk()
            ->assertJsonCount(209, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'companyId',
                    'company',
                    'name',
                    'type',
                ]],
            ])
            ->assertJsonFragment([
                'company' => 'GROUPE RTI',
                'name' => 'RTI1',
                'type' => 'Numérique',
            ]);

        $catalog = $response->json('data');
        $sortedCatalog = $catalog;
        usort($sortedCatalog, fn (array $left, array $right) => [
            $left['company'],
            $left['name'],
        ] <=> [
            $right['company'],
            $right['name'],
        ]);

        $this->assertSame($sortedCatalog, $catalog);
    }

    public function test_public_catalog_excludes_inactive_media_and_inactive_companies(): void
    {
        $activeCompany = PressCompany::create([
            'name' => 'ZZZ ENTREPRISE ACTIVE TEST',
            'is_active' => true,
        ]);
        $activeCompany->media()->create([
            'name' => 'MEDIA VISIBLE TEST',
            'type' => 'Écrit',
            'is_active' => true,
        ]);
        $activeCompany->media()->create([
            'name' => 'MEDIA INACTIF TEST',
            'type' => 'Numérique',
            'is_active' => false,
        ]);

        $inactiveCompany = PressCompany::create([
            'name' => 'ZZZ ENTREPRISE INACTIVE TEST',
            'is_active' => false,
        ]);
        $inactiveCompany->media()->create([
            'name' => 'MEDIA SOCIETE INACTIVE TEST',
            'type' => 'Écrit',
            'is_active' => true,
        ]);

        $this->getJson('/press-media')
            ->assertOk()
            ->assertJsonFragment(['name' => 'MEDIA VISIBLE TEST'])
            ->assertJsonMissing(['name' => 'MEDIA INACTIF TEST'])
            ->assertJsonMissing(['name' => 'MEDIA SOCIETE INACTIVE TEST']);
    }

    public function test_admin_catalog_requires_authentication_and_the_admin_role(): void
    {
        $this->getJson('/admin/press-companies')->assertUnauthorized();

        Sanctum::actingAs($this->makeUser('member'));

        $this->getJson('/admin/press-companies')->assertForbidden();
        $this->postJson('/admin/press-companies', [
            'name' => 'ENTREPRISE INTERDITE TEST',
        ])->assertForbidden();
    }

    public function test_admin_can_manage_companies_and_media_with_validation_and_delete_protection(): void
    {
        Sanctum::actingAs($this->makeUser('admin'));

        $companyResponse = $this->postJson('/admin/press-companies', [
            'name' => 'ENTREPRISE CRUD TEST',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'ENTREPRISE CRUD TEST')
            ->assertJsonPath('data.isActive', true)
            ->assertJsonPath('data.media', []);
        $companyId = $companyResponse->json('data.id');

        $destinationResponse = $this->postJson('/admin/press-companies', [
            'name' => 'ENTREPRISE DESTINATION TEST',
            'isActive' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('data.isActive', false);
        $destinationId = $destinationResponse->json('data.id');

        $this->postJson('/admin/press-companies', [
            'name' => 'ENTREPRISE CRUD TEST',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->putJson("/admin/press-companies/{$destinationId}", [
            'name' => 'ENTREPRISE CRUD TEST',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson("/admin/press-companies/{$companyId}/media", [
            'name' => 'MEDIA TYPE INVALIDE TEST',
            'type' => 'Télévision',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $mediaResponse = $this->postJson("/admin/press-companies/{$companyId}/media", [
            'name' => 'MEDIA CRUD TEST',
            'type' => 'Écrit',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'MEDIA CRUD TEST')
            ->assertJsonPath('data.type', 'Écrit')
            ->assertJsonPath('data.isActive', true);
        $mediaId = $mediaResponse->json('data.id');

        $this->postJson("/admin/press-companies/{$companyId}/media", [
            'name' => 'MEDIA CRUD TEST',
            'type' => 'Numérique',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->getJson('/admin/press-companies')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $mediaId,
                'name' => 'MEDIA CRUD TEST',
                'type' => 'Écrit',
                'isActive' => true,
            ]);

        $this->putJson("/admin/press-media/{$mediaId}", [
            'pressCompanyId' => $destinationId,
            'name' => 'MEDIA CRUD MODIFIE TEST',
            'type' => 'Numérique',
            'isActive' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'MEDIA CRUD MODIFIE TEST')
            ->assertJsonPath('data.type', 'Numérique')
            ->assertJsonPath('data.isActive', false);

        $this->assertDatabaseHas('press_media', [
            'id' => $mediaId,
            'press_company_id' => $destinationId,
            'name' => 'MEDIA CRUD MODIFIE TEST',
            'type' => 'Numérique',
            'is_active' => false,
        ]);

        $this->deleteJson("/admin/press-companies/{$destinationId}")
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->deleteJson("/admin/press-media/{$mediaId}")->assertNoContent();
        $this->assertDatabaseMissing('press_media', ['id' => $mediaId]);

        $this->deleteJson("/admin/press-companies/{$destinationId}")->assertNoContent();
        $this->deleteJson("/admin/press-companies/{$companyId}")->assertNoContent();

        $this->assertDatabaseMissing('press_companies', ['id' => $destinationId]);
        $this->assertDatabaseMissing('press_companies', ['id' => $companyId]);
    }

    public function test_media_update_requires_an_existing_company_and_a_supported_type(): void
    {
        Sanctum::actingAs($this->makeUser('admin'));

        $media = PressMedia::where('name', 'RTI1')->firstOrFail();

        $this->putJson("/admin/press-media/{$media->id}", [
            'pressCompanyId' => 999999,
            'name' => 'RTI1',
            'type' => 'Radio',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pressCompanyId', 'type']);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'login' => $role.'-'.Str::uuid(),
            'role' => $role,
        ]);
    }
}
