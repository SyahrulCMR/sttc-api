<?php

use App\Enums\Role;
use App\Models\OAuthClient;
use App\Models\User;

function tokenForActiveRole(object $test, User $user, OAuthClient $client): array
{
    [$verifier, $challenge] = pkcePair();
    $url = '/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);

    parse_str((string) parse_url((string) $test->get($url)->headers->get('Location'), PHP_URL_QUERY), $params);

    $token = $test->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://client.test/callback',
        'code' => $params['code'],
        'code_verifier' => $verifier,
    ])->assertOk();

    return decodeJwtPayload($token->json('access_token'));
}

it('switches active role without re-authenticating and issues a fresh token', function () {
    $user = User::factory()->withRole(Role::Dosen, Role::Kaprodi)->create();
    $client = newAuthCodeClient();

    $this->actingAs($user)->withSession(['active_role' => 'dosen']);

    $first = tokenForActiveRole($this, $user, $client);
    expect($first['active_role'])->toBe('dosen');

    // perpindahan konteks — tanpa kredensial ulang
    $authorizeStart = url('/oauth/authorize').'?client_id='.$client->id;
    $this->get('/switch-role?'.http_build_query([
        'role' => 'kaprodi',
        'redirect' => $authorizeStart,
    ]))->assertRedirect($authorizeStart);

    expect(session('active_role'))->toBe('kaprodi');

    $second = tokenForActiveRole($this, $user, $client);
    expect($second['active_role'])->toBe('kaprodi')
        ->and($second['jti'])->not->toBe($first['jti']);
});

it('forbids switching to a role the user does not hold', function () {
    $user = User::factory()->withRole(Role::Dosen)->create();

    $this->actingAs($user)
        ->get('/switch-role?'.http_build_query(['role' => 'super-admin', 'redirect' => url('/oauth/authorize')]))
        ->assertForbidden();
});

it('ignores an unsafe redirect target and falls back to root', function () {
    $user = User::factory()->withRole(Role::Dosen, Role::Kaprodi)->create();

    $this->actingAs($user)
        ->get('/switch-role?'.http_build_query(['role' => 'kaprodi', 'redirect' => 'https://evil.example/phish']))
        ->assertRedirect('/');

    expect(session('active_role'))->toBe('kaprodi');
});

it('requires authentication to switch role', function () {
    $this->get('/switch-role?'.http_build_query(['role' => 'dosen', 'redirect' => url('/oauth/authorize')]))
        ->assertRedirect('/login');
});
