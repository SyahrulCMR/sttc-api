<?php

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\QueryException;

it('soft-deletes users and keeps them reachable for audit', function () {
    $user = User::factory()->create();
    $user->delete();

    expect(User::find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id))->not->toBeNull()
        ->and(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});

it('accepts the new "inactive" status value', function () {
    $user = User::factory()->create(['status' => UserStatus::Inactive]);

    expect($user->fresh()->status)->toBe(UserStatus::Inactive);
});

it('enforces case-insensitive email uniqueness', function () {
    User::factory()->create(['email' => 'Dosen@stt-cipasung.ac.id']);

    expect(fn () => User::factory()->create(['email' => 'dosen@stt-cipasung.ac.id']))
        ->toThrow(QueryException::class);
});

it('hides two-factor columns from array output', function () {
    $user = User::factory()->create([
        'two_factor_secret' => 'SECRETVALUE',
        'two_factor_recovery_codes' => ['code-a', 'code-b'],
    ])->fresh();

    expect($user->toArray())
        ->not->toHaveKey('two_factor_secret')
        ->not->toHaveKey('two_factor_recovery_codes');

    // tetap terbaca (dan terdekripsi) secara eksplisit di sisi aplikasi
    expect($user->two_factor_recovery_codes)->toBe(['code-a', 'code-b']);
});

it('blocks SSO login for a suspended account', function () {
    User::factory()->suspended()->create([
        'identifier' => 'SUS123',
        'password' => bcrypt('Correct123!'),
    ]);

    $this->post('/sso/login', [
        'identifier' => 'SUS123',
        'password' => 'Correct123!',
        'app' => 'siakad',
    ])->assertSessionHasErrors('identifier');
});

it('blocks SSO login for an inactive account', function () {
    User::factory()->inactive()->create([
        'identifier' => 'INA123',
        'password' => bcrypt('Correct123!'),
    ]);

    $this->post('/sso/login', [
        'identifier' => 'INA123',
        'password' => 'Correct123!',
        'app' => 'siakad',
    ])->assertSessionHasErrors('identifier');
});
