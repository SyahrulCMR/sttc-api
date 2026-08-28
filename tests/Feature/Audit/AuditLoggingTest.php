<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('is append-only', function () {
    $row = LoginActivity::create(['event' => AuditEvent::LoginSuccess, 'identifier' => 'X']);

    expect(fn () => $row->update(['identifier' => 'Y']))->toThrow(RuntimeException::class)
        ->and(fn () => $row->delete())->toThrow(RuntimeException::class);
});

it('has no updated_at column', function () {
    expect(Schema::hasColumn('login_activities', 'created_at'))->toBeTrue()
        ->and(Schema::hasColumn('login_activities', 'updated_at'))->toBeFalse();
});

it('records a successful login with request metadata', function () {
    User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D1', 'password' => bcrypt('Rahasia1!')]);

    $this->withHeaders(['User-Agent' => 'PestBrowser/1.0'])
        ->post('/login', ['identifier' => 'D1', 'password' => 'Rahasia1!']);

    $entry = LoginActivity::where('event', AuditEvent::LoginSuccess->value)->firstOrFail();
    expect($entry->identifier)->toBe('D1')
        ->and($entry->user_id)->not->toBeNull()
        ->and($entry->user_agent)->toBe('PestBrowser/1.0');
});

it('records a failed login even for an unknown identifier', function () {
    $this->post('/login', ['identifier' => 'ghost', 'password' => 'nope']);

    $entry = LoginActivity::where('event', AuditEvent::LoginFailed->value)->firstOrFail();
    expect($entry->identifier)->toBe('ghost')
        ->and($entry->user_id)->toBeNull();
});

it('records a lockout', function () {
    User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D2', 'password' => bcrypt('Rahasia1!')]);

    foreach (range(1, 6) as $ignored) {
        $this->post('/login', ['identifier' => 'D2', 'password' => 'salah']);
    }

    expect(LoginActivity::where('event', AuditEvent::LoginLocked->value)->exists())->toBeTrue();
});

it('records suspension and token revocation when a user is suspended', function () {
    $user = User::factory()->withRole(Role::Dosen)->create();

    $user->update(['status' => UserStatus::Suspended]);

    expect(LoginActivity::where('user_id', $user->id)->where('event', AuditEvent::AccountSuspended->value)->exists())->toBeTrue()
        ->and(LoginActivity::where('user_id', $user->id)->where('event', AuditEvent::TokenRevoked->value)->exists())->toBeTrue();
});

it('records the break-glass procedure', function () {
    config(['security.break_glass_enabled' => true]);
    $user = User::factory()->withRole(Role::SuperAdmin)->create(['identifier' => 'SA-9']);

    $this->artisan('sso:break-glass', ['identifier' => 'SA-9', '--force' => true]);

    expect(LoginActivity::where('user_id', $user->id)->where('event', AuditEvent::BreakGlass->value)->exists())->toBeTrue()
        ->and(LoginActivity::where('user_id', $user->id)->where('event', AuditEvent::TwoFactorReset->value)->exists())->toBeTrue();
});
