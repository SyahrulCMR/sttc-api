<?php

use App\Enums\Role;
use App\Models\Role as RoleModel;
use App\Models\User;

it('provisions the 10 canonical roles via migration', function () {
    expect(RoleModel::count())->toBe(10);

    foreach (Role::cases() as $role) {
        expect(RoleModel::where('slug', $role->value)->exists())->toBeTrue();
    }
});

it('assigns and removes roles through the pivot', function () {
    $user = User::factory()->create();

    $user->assignRole(Role::Kaprodi);
    $user->assignRole(Role::Dosen);

    expect($user->hasRole(Role::Kaprodi))->toBeTrue()
        ->and($user->hasRole('dosen'))->toBeTrue()
        ->and($user->hasAnyRole([Role::Mahasiswa, Role::Dosen]))->toBeTrue()
        ->and($user->hasAnyRole([Role::Mahasiswa, Role::AdminPmb]))->toBeFalse()
        ->and($user->roles)->toHaveCount(2);

    $user->removeRole(Role::Dosen);

    expect($user->fresh()->hasRole(Role::Dosen))->toBeFalse()
        ->and($user->fresh()->roles)->toHaveCount(1);
});

it('is idempotent on repeated assignment', function () {
    $user = User::factory()->create();

    $user->assignRole(Role::Dosen);
    $user->assignRole(Role::Dosen);

    expect($user->roles()->count())->toBe(1);
});

it('flags only super-admin and admin-keuangan as sensitive', function () {
    expect(Role::SuperAdmin->isSensitive())->toBeTrue()
        ->and(Role::AdminKeuangan->isSensitive())->toBeTrue()
        ->and(Role::Dosen->isSensitive())->toBeFalse()
        ->and(Role::sensitive())->toHaveCount(2);
});

it('supports the withRole factory state', function () {
    $user = User::factory()->withRole(Role::Kaprodi, Role::Dosen)->create();

    expect($user->roles)->toHaveCount(2)
        ->and($user->hasRole(Role::Kaprodi))->toBeTrue();
});
