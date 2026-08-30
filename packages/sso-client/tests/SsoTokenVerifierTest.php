<?php

use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Http;
use Sttc\SsoClient\SsoTokenVerifier;
use Symfony\Component\HttpKernel\Exception\HttpException;

function b64u(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

/**
 * @return array{private: string, jwks: array<string, mixed>, kid: string}
 */
function rsaKeyAndJwks(string $fixture = 'rsa-a'): array
{
    $private = file_get_contents(__DIR__.'/fixtures/'.$fixture.'.pem');
    $details = openssl_pkey_get_details(openssl_pkey_get_private($private));

    $kid = b64u(hash('sha256', json_encode([
        'e' => b64u($details['rsa']['e']),
        'kty' => 'RSA',
        'n' => b64u($details['rsa']['n']),
    ]), true));

    return [
        'private' => $private,
        'kid' => $kid,
        'jwks' => ['keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => b64u($details['rsa']['n']),
            'e' => b64u($details['rsa']['e']),
        ]]],
    ];
}

function makeToken(string $privateKey, string $kid, array $overrides = []): string
{
    $claims = array_merge([
        'iss' => 'http://sso.test',
        'aud' => 'test-client',
        'sub' => '7',
        'iat' => time() - 10,
        'nbf' => time() - 10,
        'exp' => time() + 900,
        'identifier' => '2201234567',
        'name' => 'Budi',
        'email' => 'budi@stt.test',
        'roles' => ['kaprodi', 'dosen'],
        'active_role' => 'kaprodi',
        'status' => 'active',
    ], $overrides);

    return JWT::encode($claims, $privateKey, 'RS256', $kid);
}

beforeEach(function () {
    $this->rsa = rsaKeyAndJwks();
    Http::fake(['http://sso.test/oauth/jwks' => Http::response($this->rsa['jwks'])]);
});

it('verifies a well-formed token and returns its claims', function () {
    $jwt = makeToken($this->rsa['private'], $this->rsa['kid']);

    $claims = app(SsoTokenVerifier::class)->verify($jwt);

    expect($claims['active_role'])->toBe('kaprodi')
        ->and($claims['roles'])->toBe(['kaprodi', 'dosen'])
        ->and($claims['identifier'])->toBe('2201234567');
});

it('caches the JWKS (only one outbound call for repeated verifies)', function () {
    $jwt = makeToken($this->rsa['private'], $this->rsa['kid']);
    $verifier = app(SsoTokenVerifier::class);

    $verifier->verify($jwt);
    $verifier->verify($jwt);
    $verifier->verify($jwt);

    Http::assertSentCount(1);
});

it('rejects a token signed by a different key', function () {
    $other = rsaKeyAndJwks('rsa-b');
    $jwt = makeToken($other['private'], $this->rsa['kid']); // signed by other key, claims our kid

    expect(fn () => app(SsoTokenVerifier::class)->verify($jwt))
        ->toThrow(SignatureInvalidException::class);
});

it('rejects a token with the wrong issuer', function () {
    $jwt = makeToken($this->rsa['private'], $this->rsa['kid'], ['iss' => 'http://evil.test']);

    expect(fn () => app(SsoTokenVerifier::class)->verify($jwt))
        ->toThrow(HttpException::class);
});

it('rejects a token whose audience is not this client', function () {
    $jwt = makeToken($this->rsa['private'], $this->rsa['kid'], ['aud' => 'someone-else']);

    expect(fn () => app(SsoTokenVerifier::class)->verify($jwt))
        ->toThrow(HttpException::class);
});

it('honours a separate SSO_ISSUER distinct from the network address', function () {
    config(['sso-client.issuer' => 'https://sso.stt-cipasung.ac.id']);
    $jwt = makeToken($this->rsa['private'], $this->rsa['kid'], ['iss' => 'https://sso.stt-cipasung.ac.id']);

    expect(app(SsoTokenVerifier::class)->verify($jwt)['status'])->toBe('active');
});
