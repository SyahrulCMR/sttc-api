<?php

use App\Enums\Role;
use App\Models\PasswordResetRequest;
use App\Models\User;

it('redirects a guest to login', function () {
    $this->get('/admin/password-requests')->assertRedirect('/login');
});

it('forbids a non-admin user', function () {
    $user = User::factory()->withRole(Role::Dosen)->create();

    $this->actingAs($user)->get('/admin/password-requests')->assertForbidden();
});

it('lets an admin-baak user view the list, pending first', function () {
    $admin = User::factory()->withRole(Role::AdminBaak)->create();
    $subject = User::factory()->withRole(Role::Mahasiswa)->create(['identifier' => 'M1']);

    PasswordResetRequest::create(['user_id' => $subject->id, 'identifier' => 'M1', 'status' => 'rejected']);
    PasswordResetRequest::create(['user_id' => $subject->id, 'identifier' => 'M1', 'status' => 'pending']);
    PasswordResetRequest::create(['user_id' => $subject->id, 'identifier' => 'M1', 'status' => 'approved']);

    $response = $this->actingAs($admin)->get('/admin/password-requests')->assertOk();

    $body = $response->getContent();
    expect(strpos($body, 'pending'))->toBeLessThan(strpos($body, 'approved'))
        ->and(strpos($body, 'approved'))->toBeLessThan(strpos($body, 'rejected'));
});
