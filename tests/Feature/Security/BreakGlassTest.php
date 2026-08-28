<?php

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\BreakGlassCredentialNotification;
use App\Notifications\BreakGlassNoticeNotification;
use App\Services\TwoFactorService;
use App\Support\TokenDenyList;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => config(['security.break_glass_enabled' => true]));

function superAdmin(string $identifier = 'SA-1'): User
{
    return User::factory()->withRole(Role::SuperAdmin)->create([
        'identifier' => $identifier,
        'password' => Hash::make('OldPass1!'),
    ]);
}

it('refuses to run without --force', function () {
    superAdmin();

    $this->artisan('sso:break-glass', ['identifier' => 'SA-1'])
        ->assertFailed();
});

it('refuses to run when BREAK_GLASS_ENABLED is off', function () {
    config(['security.break_glass_enabled' => false]);
    superAdmin();

    $this->artisan('sso:break-glass', ['identifier' => 'SA-1', '--force' => true])
        ->assertFailed();
});

it('refuses for an identifier that is not a super admin', function () {
    User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D-1']);

    $this->artisan('sso:break-glass', ['identifier' => 'D-1', '--force' => true])
        ->assertFailed();
});

it('resets password, disables 2FA, revokes tokens and notifies', function () {
    Notification::fake();
    $target = superAdmin();
    app(TwoFactorService::class)->confirm($target, app(TwoFactorService::class)->generateSecret());
    $other = User::factory()->withRole(Role::SuperAdmin)->create(['identifier' => 'SA-2']);
    $issuedBefore = now()->subMinute()->getTimestamp();

    $this->artisan('sso:break-glass', ['identifier' => 'SA-1', '--force' => true])->assertSuccessful();

    $target->refresh();
    expect(Hash::check('OldPass1!', $target->password))->toBeFalse()
        ->and($target->two_factor_secret)->toBeNull()
        ->and($target->two_factor_confirmed_at)->toBeNull()
        ->and($target->break_glass_at)->not->toBeNull()
        ->and(app(TokenDenyList::class)->isUserRevokedSince($target->id, $issuedBefore))->toBeTrue();

    Notification::assertSentTo($target, BreakGlassCredentialNotification::class);
    Notification::assertSentTo($other, BreakGlassNoticeNotification::class);
});

it('locks an overdue account that has not been remediated', function () {
    $target = superAdmin();
    $this->artisan('sso:break-glass', ['identifier' => 'SA-1', '--force' => true]);

    $this->travel(25)->hours();
    $this->artisan('sso:enforce-break-glass-relock')->assertSuccessful();

    expect($target->fresh()->status)->toBe(UserStatus::Suspended);
});

it('clears the break-glass marker for a remediated account instead of locking', function () {
    $target = superAdmin();
    $this->artisan('sso:break-glass', ['identifier' => 'SA-1', '--force' => true]);

    $this->travel(1)->hours();
    // remediasi: ganti kata sandi + aktifkan 2FA lagi
    $target->fresh()->update(['password' => Hash::make('NewPass1!')]);
    app(TwoFactorService::class)->confirm($target->fresh(), app(TwoFactorService::class)->generateSecret());

    $this->travel(25)->hours();
    $this->artisan('sso:enforce-break-glass-relock');

    $fresh = $target->fresh();
    expect($fresh->status)->toBe(UserStatus::Active)
        ->and($fresh->break_glass_at)->toBeNull();
});

it('leaves an account within the grace window untouched', function () {
    $target = superAdmin();
    $this->artisan('sso:break-glass', ['identifier' => 'SA-1', '--force' => true]);

    $this->travel(5)->hours();
    $this->artisan('sso:enforce-break-glass-relock');

    expect($target->fresh()->status)->toBe(UserStatus::Active)
        ->and($target->fresh()->break_glass_at)->not->toBeNull();
});
