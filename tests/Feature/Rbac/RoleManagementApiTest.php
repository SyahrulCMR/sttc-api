<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\LoginActivity;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\TokenDenyList;

function superAdminToken(object $test): array
{
    $admin = User::factory()->withRole(Role::SuperAdmin)->create();

    // super-admin butuh 2FA di alur login, tapi issueAccessToken pakai actingAs (bypass)
    return [$admin, issueAccessToken($test, $admin, 'super-admin')];
}

it('forbids non-super-admin callers', function () {
    $dosen = User::factory()->withRole(Role::Dosen)->create();
    $token = issueAccessToken($this, $dosen, 'dosen');

    $this->withToken($token)->getJson('/api/v1/roles')->assertForbidden();
});

it('lists the 10 roles for a super admin', function () {
    [, $token] = superAdminToken($this);

    $this->withToken($token)->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonCount(10, 'data');
});

it('assigns a role, revokes tokens and writes an audit entry', function () {
    [, $token] = superAdminToken($this);
    $target = User::factory()->withRole(Role::Dosen)->create();
    $issuedBefore = now()->subMinute()->getTimestamp();

    $this->withToken($token)
        ->postJson("/api/v1/users/{$target->id}/roles", ['role' => 'kaprodi'])
        ->assertOk()
        ->assertJsonFragment(['slug' => 'kaprodi']);

    expect($target->fresh()->hasRole(Role::Kaprodi))->toBeTrue()
        ->and(app(TokenDenyList::class)->isUserRevokedSince($target->id, $issuedBefore))->toBeTrue()
        ->and(LoginActivity::where('user_id', $target->id)->where('event', AuditEvent::RoleAssigned->value)->exists())->toBeTrue();
});

it('revokes a role', function () {
    [, $token] = superAdminToken($this);
    $target = User::factory()->withRole(Role::Dosen, Role::Kaprodi)->create();

    $this->withToken($token)
        ->deleteJson("/api/v1/users/{$target->id}/roles/kaprodi")
        ->assertOk();

    expect($target->fresh()->hasRole(Role::Kaprodi))->toBeFalse();
});

it('refuses to revoke the last super admin', function () {
    [$admin, $token] = superAdminToken($this);

    $this->withToken($token)
        ->deleteJson("/api/v1/users/{$admin->id}/roles/super-admin")
        ->assertStatus(422);

    expect($admin->fresh()->hasRole(Role::SuperAdmin))->toBeTrue();
});

it('refuses to revoke your own super-admin role even if others exist', function () {
    [$admin, $token] = superAdminToken($this);
    User::factory()->withRole(Role::SuperAdmin)->create(); // second super admin

    $this->withToken($token)
        ->deleteJson("/api/v1/users/{$admin->id}/roles/super-admin")
        ->assertStatus(422);
});

it('404s for an unknown role slug', function () {
    [, $token] = superAdminToken($this);
    $target = User::factory()->withRole(Role::Dosen)->create();

    $this->withToken($token)
        ->deleteJson("/api/v1/users/{$target->id}/roles/not-a-role")
        ->assertNotFound();
});

it('resets a users 2FA', function () {
    [, $token] = superAdminToken($this);
    $target = User::factory()->withRole(Role::AdminKeuangan)->create();
    app(TwoFactorService::class)->confirm($target, app(TwoFactorService::class)->generateSecret());

    $this->withToken($token)
        ->postJson("/api/v1/users/{$target->id}/two-factor/reset")
        ->assertOk();

    expect($target->fresh()->hasTwoFactorEnabled())->toBeFalse()
        ->and(LoginActivity::where('user_id', $target->id)->where('event', AuditEvent::TwoFactorReset->value)->exists())->toBeTrue();
});
