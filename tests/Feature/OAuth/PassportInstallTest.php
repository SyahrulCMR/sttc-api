<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('auth:api')->get('/_test/api-user', fn () => response()->json(['id' => request()->user()?->id]));
});

it('registers the oauth routes and disables password + device grants', function () {
    $client = newAuthCodeClient();

    // password grant -> dimatikan
    $this->post('/oauth/token', [
        'grant_type' => 'password',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'username' => 'x@x.test',
        'password' => 'secret',
    ])->assertStatus(400)
        ->assertJsonPath('error', 'unsupported_grant_type');

    // device code grant -> dimatikan di authorization server
    $this->post('/oauth/token', [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'device_code' => 'anything',
    ])->assertStatus(400)
        ->assertJsonPath('error', 'unsupported_grant_type');

    // implicit grant -> dimatikan
    $this->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'token',
    ]))->assertStatus(400);
});

it('issues a JWT access token through the authorization code + PKCE flow', function () {
    $user = User::factory()->withRole(Role::Mahasiswa)->create();
    $client = newAuthCodeClient();
    [$verifier, $challenge] = pkcePair();

    $redirect = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'scope' => '',
        'state' => 'xyz',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    // first-party client -> skip consent -> redirect langsung dengan ?code=
    $redirect->assertRedirect();
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $params);
    expect($params)->toHaveKey('code')->and($params['state'])->toBe('xyz');

    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://client.test/callback',
        'code' => $params['code'],
        'code_verifier' => $verifier,
    ]);

    $token->assertOk()->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token']);
    expect($token->json('token_type'))->toBe('Bearer')
        ->and($token->json('expires_in'))->toBeGreaterThan(600)
        ->and($token->json('expires_in'))->toBeLessThanOrEqual(900);

    // access token adalah JWT RS256 dengan klaim standar
    [$header, $payload] = explode('.', $token->json('access_token'));
    $header = json_decode(base64_decode(strtr($header, '-_', '+/')), true);
    $payload = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

    expect($header['alg'])->toBe('RS256')
        ->and(Arr::wrap($payload['aud']))->toContain($client->id)
        ->and($payload['sub'])->toBe((string) $user->id)
        ->and($payload)->toHaveKeys(['jti', 'exp', 'iat', 'nbf', 'scopes']);

    // token dipakai di guard auth:api
    $this->withToken($token->json('access_token'))
        ->getJson('/_test/api-user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

it('rejects the token exchange when the PKCE verifier is wrong', function () {
    $user = User::factory()->withRole(Role::Mahasiswa)->create();
    $client = newAuthCodeClient();
    [, $challenge] = pkcePair();

    $redirect = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $params);

    $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://client.test/callback',
        'code' => $params['code'],
        'code_verifier' => 'the-wrong-verifier-the-wrong-verifier-the-wrong-verifier',
    ])->assertStatus(400);
});

it('keeps the legacy Sanctum PAT flow working (regression)', function () {
    $user = User::factory()->create();

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $this->withToken($login->json('data.token'))
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});
