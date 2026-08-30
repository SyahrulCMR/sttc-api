<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\LoginActivity;
use App\Models\User;
use App\Support\TokenDenyList;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Task 2c-1 — TTL refresh per-role (14 hari umum / 8 jam role sensitif) + rotasi.
 */
function issueTokenResponse(object $test, User $user, ?string $activeRole = null, ?object $client = null): array
{
    $client ??= newAuthCodeClient();
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

    return $test->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://client.test/callback',
        'code' => $params['code'],
        'code_verifier' => $verifier,
    ])->assertOk()->json();
}

function refreshRowFor(string $accessToken): object
{
    $jti = decodeJwtPayload($accessToken)['jti'];

    return DB::table('oauth_refresh_tokens')->where('access_token_id', $jti)->firstOrFail();
}

it('gives a sensitive-role token an 8-hour refresh TTL', function () {
    config(['passport.ttl.refresh_sensitive' => 8 * 60]);
    $user = User::factory()->withRole(Role::AdminKeuangan)->create();

    $row = refreshRowFor(issueTokenResponse($this, $user)['access_token']);

    expect(Carbon::parse($row->expires_at)->diffInMinutes(now(), true))
        ->toBeGreaterThan(8 * 60 - 5)
        ->toBeLessThan(8 * 60 + 5);
});

it('keeps the default 14-day refresh TTL for a normal role', function () {
    $user = User::factory()->withRole(Role::Mahasiswa)->create();

    $row = refreshRowFor(issueTokenResponse($this, $user)['access_token']);

    expect(Carbon::parse($row->expires_at)->diffInDays(now(), true))->toBeGreaterThan(13);
});

it('shortens the TTL only for the sensitive scope on a multi-role token', function () {
    config(['passport.ttl.refresh_sensitive' => 8 * 60]);
    $user = User::factory()->withRole(Role::SuperAdmin, Role::Dosen)->create();

    // active_role dosen -> tetap 14 hari
    $normal = refreshRowFor(issueTokenResponse($this, $user, activeRole: 'dosen')['access_token']);
    expect(Carbon::parse($normal->expires_at)->diffInDays(now(), true))->toBeGreaterThan(13);

    // active_role super-admin -> 8 jam
    $sensitive = refreshRowFor(issueTokenResponse($this, $user, activeRole: 'super-admin')['access_token']);
    expect(Carbon::parse($sensitive->expires_at)->diffInHours(now(), true))->toBeLessThan(9);
});

it('rotates the refresh token on use (old one is revoked)', function () {
    $user = User::factory()->withRole(Role::Mahasiswa)->create();
    $client = newAuthCodeClient();

    $tokens = issueTokenResponse($this, $user, client: $client);
    $oldRow = refreshRowFor($tokens['access_token']);

    $refreshed = $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
    ])->assertOk()->json();

    expect(DB::table('oauth_refresh_tokens')->where('id', $oldRow->id)->value('revoked'))->toBeTruthy()
        ->and($refreshed['refresh_token'])->not->toBe($tokens['refresh_token']);
})->group('rotation');

it('treats reuse of a rotated refresh token as a compromise (deny-list + audit)', function () {
    $user = User::factory()->withRole(Role::Mahasiswa)->create();
    $client = newAuthCodeClient();

    $tokens = issueTokenResponse($this, $user, client: $client);
    $issuedAt = now()->subSecond()->getTimestamp();

    // pemakaian sah -> rotasi
    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
    ])->assertOk();

    // replay token lama yang sudah dirotasi
    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
    ])->assertStatus(400);

    expect(app(TokenDenyList::class)->isUserRevokedSince($user->id, $issuedAt))->toBeTrue()
        ->and(LoginActivity::query()
            ->where('user_id', $user->id)
            ->where('event', AuditEvent::TokenRevoked->value)
            ->where('context->reason', 'refresh_reuse')
            ->exists())->toBeTrue();
})->group('rotation');
