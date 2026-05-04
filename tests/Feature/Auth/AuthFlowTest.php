<?php

use App\Models\User;

it('registers a new user and returns a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['user' => ['id', 'name', 'email'], 'token', 'token_type'],
        ]);

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('rejects invalid login credentials', function () {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('Correct123!'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'john@example.com',
        'password' => 'WrongPass!',
    ])->assertUnauthorized()
        ->assertJson(['success' => false]);
});

it('returns the authenticated user via /me', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});
