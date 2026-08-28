<?php

use App\Enums\Role;
use App\Models\User;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

it('publishes a single RS256 signing key at /oauth/jwks', function () {
    $response = $this->getJson('/oauth/jwks')->assertOk();

    $key = $response->json('keys.0');

    expect($key)->toMatchArray(['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256'])
        ->and($key['kid'])->toBeString()
        ->and($key['n'])->toBeString()
        ->and($key['e'])->toBeString();

    expect($response->headers->get('Cache-Control'))->toContain('max-age=3600')->toContain('public');
});

it('stamps issued tokens with the kid advertised in the JWKS', function () {
    $user = User::factory()->withRole(Role::Dosen)->create();
    $client = newAuthCodeClient();
    [$verifier, $challenge] = pkcePair();

    parse_str((string) parse_url((string) $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->headers->get('Location'), PHP_URL_QUERY), $params);

    $accessToken = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'https://client.test/callback',
        'code' => $params['code'],
        'code_verifier' => $verifier,
    ])->json('access_token');

    [$header] = explode('.', $accessToken);
    $header = json_decode(base64_decode(strtr($header, '-_', '+/')), true);

    $jwksKid = $this->getJson('/oauth/jwks')->json('keys.0.kid');

    expect($header['kid'])->toBe($jwksKid);

    // Verifikasi tanda tangan secara lokal (pola resource server).
    $token = (new Parser(new JoseEncoder))->parse($accessToken);
    $publicKey = InMemory::file(Passport::keyPath('oauth-public.key'));

    expect((new Validator)->validate($token, new SignedWith(new Sha256, $publicKey)))->toBeTrue();
});
