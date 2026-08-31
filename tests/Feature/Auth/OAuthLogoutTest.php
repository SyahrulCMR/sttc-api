<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\LoginActivity;
use App\Models\User;

it('ends the sttc-api web session and redirects to login', function () {
    $user = User::factory()->withRole(Role::Dosen)->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/login');

    expect($this->isAuthenticated())->toBeFalse()
        ->and(LoginActivity::where('user_id', $user->id)->where('event', AuditEvent::Logout->value)->exists())->toBeTrue();
});

it('requires authentication', function () {
    $this->post('/logout')->assertRedirect('/login');
});
