<?php

use App\Enums\Role;
use App\Models\OAuthClient;
use App\Models\User;
use Database\Seeders\PassportClientSeeder;
use Illuminate\Support\Str;

it('provisions the two internal first-party clients', function () {
    $this->seed(PassportClientSeeder::class);

    $siakad = OAuthClient::query()->where('name', 'sttc-siakad')->firstOrFail();
    $website = OAuthClient::query()->where('name', 'sttc-website')->firstOrFail();

    expect($siakad->firstParty())->toBeTrue()
        ->and($website->firstParty())->toBeTrue()
        ->and($siakad->grant_types)->toBe(['authorization_code', 'refresh_token'])
        ->and($siakad->redirect_uris)->not->toBeEmpty()
        ->and($siakad->confidential())->toBeTrue();
});

it('is idempotent across repeated seeding', function () {
    $this->seed(PassportClientSeeder::class);
    $this->seed(PassportClientSeeder::class);

    expect(OAuthClient::query()->whereIn('name', ['sttc-siakad', 'sttc-website'])->count())->toBe(2);
});

it('lets a seeded client complete PKCE without a consent screen', function () {
    $this->seed(PassportClientSeeder::class);
    $client = OAuthClient::query()->where('name', 'sttc-siakad')->firstOrFail();
    $user = User::factory()->withRole(Role::Mahasiswa)->create();

    $verifier = Str::random(80);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $redirectUri = $client->redirect_uris[0];

    $redirect = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $redirect->assertRedirect();
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $params);

    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => 'sttc-siakad-local-dev-secret',
        'redirect_uri' => $redirectUri,
        'code' => $params['code'],
        'code_verifier' => $verifier,
    ])->assertOk();

    expect($token->json('access_token'))->toBeString();
});

it('skips seeding in the production environment', function () {
    $this->app['env'] = 'production';

    (new PassportClientSeeder)->run();

    expect(OAuthClient::query()->count())->toBe(0);
});
