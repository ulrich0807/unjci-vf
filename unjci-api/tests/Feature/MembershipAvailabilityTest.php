<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_installation_starts_closed_and_the_status_is_public(): void
    {
        $defaults = require config_path('membership.php');
        config(['membership.requests_closed' => $defaults['requests_closed']]);
        $this->assertTrue($defaults['requests_closed']);
        $this->getJson('/membership/availability')->assertOk()
            ->assertJsonPath('requests_closed', true)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_only_main_admins_can_change_availability(): void
    {
        $this->putJson('/admin/membership/availability', ['requests_closed' => false])->assertUnauthorized();
        foreach (['member', 'scanner', 'media_admin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['login' => $role, 'role' => $role, 'permissions' => ['manage_members']]));
            $this->putJson('/admin/membership/availability', ['requests_closed' => false])->assertForbidden();
        }
        Storage::disk('local')->assertMissing('settings/membership.json');
    }

    public function test_admin_can_reopen_and_close_requests_and_the_saved_choice_survives_config_changes(): void
    {
        config(['membership.requests_closed' => true]);
        Sanctum::actingAs(User::factory()->create(['login' => 'admin', 'role' => 'admin']));
        $this->putJson('/admin/membership/availability', ['requests_closed' => false])->assertOk()
            ->assertJsonPath('requests_closed', false);
        Storage::disk('local')->assertExists('settings/membership.json');
        $this->getJson('/membership/availability')->assertOk()->assertJsonPath('requests_closed', false);
        $this->getJson('/admin/dashboard')->assertOk()->assertJsonPath('membershipAvailability.requests_closed', false);
        foreach (['adhesion', 'renewal'] as $type) {
            $this->postJson('/members/apply', ['requestType' => $type])->assertUnprocessable();
        }

        $this->putJson('/admin/membership/availability', ['requests_closed' => true])->assertOk()
            ->assertJsonPath('requests_closed', true);
        config(['membership.requests_closed' => false]);
        $this->getJson('/membership/availability')->assertOk()->assertJsonPath('requests_closed', true);
        Http::fake();
        foreach (['adhesion', 'renewal'] as $type) {
            $this->postJson('/members/apply', ['requestType' => $type])->assertForbidden()
                ->assertJsonPath('code', 'membership_closed');
            $this->postJson('/member/payment/wave/initiate', ['paymentType' => $type])->assertForbidden()
                ->assertJsonPath('code', 'membership_closed');
        }
        Http::assertNothingSent();
    }

    public function test_invalid_updates_do_not_change_the_saved_choice_and_corrupted_settings_fail_closed(): void
    {
        Sanctum::actingAs(User::factory()->create(['login' => 'admin', 'role' => 'admin']));
        $this->putJson('/admin/membership/availability', ['requests_closed' => true])->assertOk();
        $this->putJson('/admin/membership/availability', ['requests_closed' => 'perhaps'])->assertUnprocessable();
        $this->getJson('/membership/availability')->assertJsonPath('requests_closed', true);
        Storage::disk('local')->put('settings/membership.json', '{broken');
        $this->getJson('/membership/availability')->assertJsonPath('requests_closed', true);
    }
}
