<?php

namespace Tests\Feature;

use App\Models\LoginAudit;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class LoginAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_audited_without_sensitive_data(): void
    {
        $user = $this->makeUser('admin', 'audit-admin', 'audit-admin@example.test');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('User-Agent', 'UNJCI-Audit-Test/1.0')
            ->postJson('/login', [
                'login' => 'audit-admin',
                'password' => 'password123',
            ])
            ->assertOk()
            ->assertJsonStructure(['token']);

        $audit = LoginAudit::sole();

        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('audit-admin', $audit->login);
        $this->assertTrue($audit->success);
        $this->assertNull($audit->failure_reason);
        $this->assertSame('203.0.113.10', $audit->ip_address);
        $this->assertSame('UNJCI-Audit-Test/1.0', $audit->user_agent);
        $this->assertFalse(Schema::hasColumn('login_audits', 'password'));
        $this->assertFalse(Schema::hasColumn('login_audits', 'token'));

        $user->delete();
        $this->assertNull($audit->fresh()->user_id);
    }

    public function test_invalid_credentials_are_audited_and_long_inputs_are_truncated(): void
    {
        $login = str_repeat('L', 300);
        $userAgent = str_repeat('A', 1500);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->withHeader('User-Agent', $userAgent)
            ->postJson('/login', [
                'login' => $login,
                'password' => 'incorrect-password',
            ])
            ->assertUnauthorized();

        $audit = LoginAudit::sole();

        $this->assertNull($audit->user_id);
        $this->assertFalse($audit->success);
        $this->assertSame('invalid_credentials', $audit->failure_reason);
        $this->assertSame(255, mb_strlen($audit->login));
        $this->assertSame(1000, mb_strlen($audit->user_agent));
        $this->assertSame('198.51.100.20', $audit->ip_address);
    }

    public function test_invalid_password_audit_keeps_the_matched_user_reference(): void
    {
        $user = $this->makeUser('admin', 'known-admin', 'known-admin@example.test');

        $this->postJson('/login', [
            'login' => $user->login,
            'password' => 'incorrect-password',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('login_audits', [
            'user_id' => $user->id,
            'login' => $user->login,
            'success' => false,
            'failure_reason' => 'invalid_credentials',
        ]);
    }

    public function test_pending_member_email_login_is_audited(): void
    {
        $user = $this->makePendingMember();

        $this->postJson('/login', [
            'login' => $user->email,
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('login_audits', [
            'user_id' => $user->id,
            'login' => $user->email,
            'success' => true,
            'failure_reason' => null,
        ]);
    }

    public function test_audit_write_failure_never_blocks_login(): void
    {
        $user = $this->makeUser('admin', 'resilient-admin', 'resilient-admin@example.test');
        LoginAudit::creating(function () {
            throw new RuntimeException('Audit storage unavailable.');
        });

        try {
            $this->postJson('/login', [
                'login' => $user->login,
                'password' => 'password123',
            ])
                ->assertOk()
                ->assertJsonStructure(['token']);
        } finally {
            LoginAudit::flushEventListeners();
        }

        $this->assertDatabaseCount('login_audits', 0);
    }

    public function test_login_audits_endpoint_requires_admin_and_valid_filters(): void
    {
        $this->getJson('/admin/login-audits')->assertUnauthorized();

        Sanctum::actingAs($this->makeUser('member', 'ordinary-member', 'ordinary@example.test'));
        $this->getJson('/admin/login-audits')->assertForbidden();

        Sanctum::actingAs($this->makeUser('admin', 'listing-admin', 'listing-admin@example.test'));
        $this->getJson('/admin/login-audits?status=unknown&perPage=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'perPage']);
    }

    public function test_login_endpoint_limits_brute_force_attempts(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/login', [
                'login' => 'unknown-login',
                'password' => 'incorrect-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/login', [
            'login' => 'unknown-login',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();

        $this->postJson('/login', [
            'login' => 'another-unknown-login',
            'password' => 'incorrect-password',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('login_audits', 6);
    }

    public function test_admin_can_search_filter_and_paginate_recent_audits(): void
    {
        $admin = $this->makeUser('admin', 'audit-list-admin', 'audit-list-admin@example.test');
        $matchedUser = $this->makeUser('scanner', 'matched-scanner', 'matched-scanner@example.test');
        Sanctum::actingAs($admin);

        $older = LoginAudit::create([
            'login' => 'needle-old',
            'success' => false,
            'failure_reason' => 'invalid_credentials',
            'ip_address' => '192.0.2.1',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);
        $newer = LoginAudit::create([
            'user_id' => $matchedUser->id,
            'login' => 'needle-new',
            'success' => false,
            'failure_reason' => 'email_verification_required',
            'ip_address' => '192.0.2.2',
            'user_agent' => 'Recent agent',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        LoginAudit::create([
            'login' => 'needle-success',
            'success' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/admin/login-audits?search=needle&status=failure&perPage=1&page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.login', $newer->login)
            ->assertJsonPath('data.0.userId', $matchedUser->id)
            ->assertJsonPath('data.0.userName', $matchedUser->name)
            ->assertJsonPath('data.0.userEmail', $matchedUser->email)
            ->assertJsonPath('data.0.role', 'scanner')
            ->assertJsonPath('data.0.success', false)
            ->assertJsonPath('data.0.reason', 'email_verification_required')
            ->assertJsonPath('meta.currentPage', 1)
            ->assertJsonPath('meta.lastPage', 2)
            ->assertJsonPath('meta.perPage', 1)
            ->assertJsonPath('meta.total', 2);

        $this->assertSame([
            'id',
            'login',
            'userId',
            'userName',
            'userEmail',
            'role',
            'success',
            'reason',
            'ipAddress',
            'userAgent',
            'createdAt',
        ], array_keys($response->json('data.0')));

        $this->getJson('/admin/login-audits?search=needle&status=failure&perPage=1&page=2')
            ->assertOk()
            ->assertJsonPath('data.0.login', $older->login);
    }

    private function makeUser(string $role, string $login, string $email): User
    {
        return User::create([
            'name' => "Utilisateur {$role}",
            'email' => $email,
            'login' => $login,
            'password' => Hash::make('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }

    private function makePendingMember(): User
    {
        $user = $this->makeUser('member', 'UJ25-09001', 'pending-audit@example.test');

        Member::create([
            'user_id' => $user->id,
            'last_name' => 'TEST',
            'first_name' => 'Audit',
            'birth_date' => '1990-01-01',
            'birth_place' => 'Abidjan',
            'phone' => '0102030405',
            'personal_email' => $user->email,
            'request_type' => 'adhesion',
            'current_member_number' => $user->login,
            'member_number' => $user->login,
            'professional_status' => 'Journaliste',
            'employers' => 'Entreprise Test',
            'media_name' => 'Média Test',
            'media_type' => 'Numérique',
            'function_title' => 'Rédacteur',
            'press_card_number' => '9001JP',
            'press_card_expiry' => '2030-12-31',
            'press_card_recto' => 'tests/recto.jpg',
            'press_card_verso' => 'tests/verso.jpg',
            'photo_file_path' => 'tests/photo.jpg',
            'declaration_accepted' => true,
            'privacy_accepted' => true,
        ]);

        DB::table('email_verification_otps')->insert([
            'email' => $user->email,
            'code_hash' => Hash::make('verification-pending'),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'last_sent_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
