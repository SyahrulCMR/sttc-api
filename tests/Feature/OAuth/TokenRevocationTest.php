<?php

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\TokenDenyList;
use Illuminate\Support\Facades\Route;

function sensitiveToken(object $test, User $user): string
{
    $client = newAuthCodeClient();
    [$verifier, $challenge] = pkcePair();

    $test->actingAs($user)->withSession(['active_role' => 'super-admin']);

    parse_str((string) parse_url((string) $test->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->headers->get('Location'), PHP_URL_QUERY), $params);

    return $test->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://client.test/callback',
        'code' => $params['code'],
        'code_verifier' => $verifier,
    ])->assertOk()->json('access_token');
}

beforeEach(function () {
    Route::middleware(['auth:api', 'token.not.revoked'])
        ->get('/_test/sensitive', fn () => response()->json(['ok' => true]));
});

it('accepts a sensitive-role token that has not been revoked', function () {
    $user = User::factory()->withRole(Role::SuperAdmin)->create();
    $token = sensitiveToken($this, $user);

    $this->withToken($token)->getJson('/_test/sensitive')->assertOk();
});

it('rejects a sensitive-role token after all user tokens are revoked', function () {
    $user = User::factory()->withRole(Role::SuperAdmin)->create();
    $token = sensitiveToken($this, $user);

    // token diterbitkan "sekarang" -> mundurkan waktu 1 detik agar revokeAllForUser (iat < stamp) menang
    $this->travel(2)->seconds();
    app(TokenDenyList::class)->revokeAllForUser($user->id);

    $this->withToken($token)->getJson('/_test/sensitive')->assertUnauthorized();
});

it('rejects a single revoked jti but leaves other tokens working', function () {
    $user = User::factory()->withRole(Role::SuperAdmin)->create();
    $tokenA = sensitiveToken($this, $user);
    $tokenB = sensitiveToken($this, $user);

    $jtiA = decodeJwtPayload($tokenA)['jti'];
    app(TokenDenyList::class)->revokeToken($jtiA, now()->addMinutes(15));

    $this->withToken($tokenA)->getJson('/_test/sensitive')->assertUnauthorized();
    $this->withToken($tokenB)->getJson('/_test/sensitive')->assertOk();
});

it('does not check the deny-list for a non-sensitive role token', function () {
    $user = User::factory()->withRole(Role::Dosen)->create();
    $client = newAuthCodeClient();
    [$verifier, $challenge] = pkcePair();

    $this->actingAs($user);
    parse_str((string) parse_url((string) $this->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->headers->get('Location'), PHP_URL_QUERY), $params);

    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://client.test/callback',
        'code' => $params['code'],
        'code_verifier' => $verifier,
    ])->json('access_token');

    $this->travel(2)->seconds();
    app(TokenDenyList::class)->revokeAllForUser($user->id);

    // role umum: tidak dicek -> tetap OK
    $this->withToken($token)->getJson('/_test/sensitive')->assertOk();
});

it('revokes tokens automatically when a user is suspended', function () {
    $user = User::factory()->withRole(Role::SuperAdmin)->create();
    $token = sensitiveToken($this, $user);

    $this->travel(2)->seconds();
    $user->update(['status' => UserStatus::Suspended]);

    $this->withToken($token)->getJson('/_test/sensitive')->assertUnauthorized();
});
