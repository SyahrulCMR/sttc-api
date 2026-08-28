<?php

use App\Enums\Role;
use App\Models\User;

function issueUserToken(object $test, User $user, ?string $activeRole = null): array
{
    $client = newAuthCodeClient();
    [$verifier, $challenge] = pkcePair();

    $test->actingAs($user);
    if ($activeRole !== null) {
        $test->withSession(['active_role' => $activeRole]);
    }

    $redirect = $test->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $params);

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

it('embeds issuer, roles, status and the auto-resolved active_role for a single-role user', function () {
    $user = User::factory()->withRole(Role::Dosen)->create();

    $payload = issueUserToken($this, $user);

    expect($payload['iss'])->toBe(config('app.url'))
        ->and($payload['roles'])->toBe(['dosen'])
        ->and($payload['status'])->toBe('active')
        ->and($payload['active_role'])->toBe('dosen')
        ->and($payload['scopes'])->toContain('role:dosen');
});

it('uses the session-selected active_role for a multi-role user', function () {
    $user = User::factory()->withRole(Role::Kaprodi, Role::Dosen)->create();

    $payload = issueUserToken($this, $user, activeRole: 'kaprodi');

    expect($payload['active_role'])->toBe('kaprodi')
        ->and($payload['roles'])->toEqualCanonicalizing(['kaprodi', 'dosen'])
        ->and($payload['scopes'])->toContain('role:kaprodi');
});

it('carries the real status claim for a suspended user (login-time enforcement is separate)', function () {
    $user = User::factory()->suspended()->withRole(Role::Mahasiswa)->create();

    expect(issueUserToken($this, $user)['status'])->toBe('suspended');
});
