<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:super-admin,admin-baak'])
        ->get('/_test/rbac', fn () => response('ok'));

    Route::middleware(['web', 'role:dosen'])
        ->get('/_test/rbac-noauth', fn () => response('ok'));
});

it('allows a user whose single role matches', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::AdminBaak);

    $this->actingAs($user)->get('/_test/rbac')->assertOk();
});

it('forbids a user without an allowed role', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Dosen);

    $this->actingAs($user)->get('/_test/rbac')->assertForbidden();
});

it('forbids a multi-role user until an active role is selected', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::AdminBaak);
    $user->assignRole(Role::Dosen);

    $this->actingAs($user)->get('/_test/rbac')->assertForbidden();

    $this->actingAs($user)
        ->withSession(['active_role' => 'admin-baak'])
        ->get('/_test/rbac')->assertOk();
});

it('rejects an active role the user does not actually hold', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Dosen);
    $user->assignRole(Role::Kaprodi);

    $this->actingAs($user)
        ->withSession(['active_role' => 'super-admin'])
        ->get('/_test/rbac')->assertForbidden();
});

it('returns 401 when unauthenticated', function () {
    $this->get('/_test/rbac-noauth')->assertUnauthorized();
});
