<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipClosureTest extends TestCase
{
    public function test_closed_session_refuses_new_applications_and_wave_checkouts(): void
    {
        config(['membership.requests_closed' => true]);
        $message = config('membership.closure_message');
        Http::fake();

        foreach (['adhesion', 'renewal'] as $type) {
            $this->postJson('/members/apply', ['requestType' => $type])
                ->assertForbidden()->assertJsonPath('message', $message);
        }

        Sanctum::actingAs(new User());

        foreach (['adhesion', 'renewal'] as $type) {
            $this->postJson('/member/payment/wave/initiate', ['paymentType' => $type])
                ->assertForbidden()->assertJsonPath('message', $message);
        }

        Http::assertNothingSent();
    }
}
