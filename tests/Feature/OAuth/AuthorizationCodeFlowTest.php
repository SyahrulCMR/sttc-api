<?php

use App\Enums\Role;
use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Testing\TestResponse;

function authorizeUrl(OAuthClient $client, string $challenge, ?string $scope = null): string
{
    return '/oauth/authorize?'.http_build_query(array_filter([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'state' => 'xyz',
        'scope' => $scope,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ], fn ($v) => $v !== null && $v !== ''));
}

function queryOf(TestResponse $response): array
{
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $params);

    return $params;
}

it('sends an unauthenticated authorize request to the login page', function () {
    $client = newAuthCodeClient();
    [, $challenge] = pkcePair();

    $this->get(authorizeUrl($client, $challenge))->assertRedirect('/login');
});

it('completes the flow after form login for a single-role user', function () {
    User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D001', 'password' => bcrypt('Rahasia1!')]);
    $client = newAuthCodeClient();
    [$verifier, $challenge] = pkcePair();
    $url = authorizeUrl($client, $challenge);

    $this->get($url)->assertRedirect('/login');

    $afterLogin = $this->post('/login', ['identifier' => 'D001', 'password' => 'Rahasia1!']);
    $afterLogin->assertRedirectContains('/oauth/authorize');

    $params = queryOf($this->get($afterLogin->headers->get('Location')));
    expect($params)->toHaveKey('code');

    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://client.test/callback',
        'code' => $params['code'],
        'code_verifier' => $verifier,
    ])->assertOk();

    $payload = decodeJwtPayload($token->json('access_token'));
    expect($payload['active_role'])->toBe('dosen')
        ->and($payload['roles'])->toBe(['dosen'])
        ->and($params['state'])->toBe('xyz');
});

it('shows the role picker for a multi-role user and honours the choice', function () {
    User::factory()->withRole(Role::Dosen, Role::Kaprodi)->create(['identifier' => 'K001', 'password' => bcrypt('Rahasia1!')]);
    $client = newAuthCodeClient();
    [$verifier, $challenge] = pkcePair();
    $url = authorizeUrl($client, $challenge);

    $this->get($url)->assertRedirect('/login');
    $authorizeUrl = $this->post('/login', ['identifier' => 'K001', 'password' => 'Rahasia1!'])
        ->headers->get('Location');

    // authorize -> multi-role & belum pilih -> picker
    $this->get($authorizeUrl)->assertRedirect(route('role.select'));
    $this->get(route('role.select'))->assertOk()->assertSee('Kaprodi');

    $this->post('/select-role', ['role' => 'kaprodi'])->assertRedirectContains('/oauth/authorize');

    $params = queryOf($this->get($authorizeUrl));
    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://client.test/callback',
        'code' => $params['code'],
        'code_verifier' => $verifier,
    ])->assertOk();

    expect(decodeJwtPayload($token->json('access_token'))['active_role'])->toBe('kaprodi');
});

it('rejects a role the user does not hold at the picker', function () {
    $user = User::factory()->withRole(Role::Dosen, Role::Kaprodi)->create();

    $this->actingAs($user)->post('/select-role', ['role' => 'super-admin'])->assertForbidden();
});

it('rejects bad credentials at the OAuth login form and eventually throttles', function () {
    User::factory()->withRole(Role::Dosen)->create(['identifier' => 'D009', 'password' => bcrypt('Rahasia1!')]);

    foreach (range(1, 5) as $ignored) {
        $this->from('/login')->followingRedirects()
            ->post('/login', ['identifier' => 'D009', 'password' => 'salah'])
            ->assertSee('NIM/NIDN atau kata sandi yang Anda masukkan salah.');
    }

    $this->from('/login')->followingRedirects()
        ->post('/login', ['identifier' => 'D009', 'password' => 'Rahasia1!'])
        ->assertSee('Coba lagi dalam');
});

it('blocks a suspended user at the OAuth login form', function () {
    User::factory()->suspended()->withRole(Role::Dosen)->create(['identifier' => 'S009', 'password' => bcrypt('Rahasia1!')]);

    $this->from('/login')->followingRedirects()
        ->post('/login', ['identifier' => 'S009', 'password' => 'Rahasia1!'])
        ->assertSee('ditangguhkan');
});
