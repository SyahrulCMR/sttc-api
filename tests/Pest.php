<?php

use App\Models\OAuthClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * PKCE verifier + S256 challenge.
 *
 * @return array{0: string, 1: string}
 */
function pkcePair(): array
{
    $verifier = Str::random(80);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [$verifier, $challenge];
}

/**
 * Buat Auth Code + PKCE client (first-party, confidential) untuk test.
 */
function newAuthCodeClient(): OAuthClient
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Test Client',
        redirectUris: ['https://client.test/callback'],
        confidential: true,
    );
}

/**
 * Decode payload JWT tanpa verifikasi tanda tangan (untuk assertion di test).
 *
 * @return array<string, mixed>
 */
function decodeJwtPayload(string $jwt): array
{
    [, $payload] = explode('.', $jwt);

    return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
}

/**
 * Terbitkan access token OAuth untuk $user lewat alur PKCE, dengan role aktif opsional.
 */
function issueAccessToken(object $test, User $user, ?string $activeRole = null): string
{
    $client = newAuthCodeClient();
    [$verifier, $challenge] = pkcePair();

    $test->actingAs($user);
    if ($activeRole !== null) {
        $test->withSession(['active_role' => $activeRole]);
    }

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
    ])->json('access_token');
}
