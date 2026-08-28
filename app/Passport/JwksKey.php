<?php

namespace App\Passport;

use Laravel\Passport\Passport;
use RuntimeException;

/**
 * Merepresentasikan kunci publik RS256 Passport sebagai JWK (RFC 7517) +
 * thumbprint `kid` (RFC 7638). Dipakai oleh endpoint /oauth/jwks dan oleh
 * App\Passport\AccessToken untuk menandai header `kid` pada setiap token.
 */
class JwksKey
{
    /** @var array{n: string, e: string}|null */
    private ?array $components = null;

    public function kid(): string
    {
        ['n' => $n, 'e' => $e] = $this->components();

        // RFC 7638 JWK thumbprint: SHA-256 atas JSON kanonis {e, kty, n}.
        $canonical = '{"e":"'.$e.'","kty":"RSA","n":"'.$n.'"}';

        return $this->base64Url(hash('sha256', $canonical, true));
    }

    /**
     * @return array<string, string>
     */
    public function jwk(): array
    {
        ['n' => $n, 'e' => $e] = $this->components();

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $this->kid(),
            'n' => $n,
            'e' => $e,
        ];
    }

    /**
     * @return array{n: string, e: string}
     */
    private function components(): array
    {
        if ($this->components !== null) {
            return $this->components;
        }

        $pem = config('passport.public_key')
            ?: @file_get_contents(Passport::keyPath('oauth-public.key'));

        if (! $pem) {
            throw new RuntimeException('Kunci publik Passport tidak ditemukan. Jalankan `php artisan passport:keys`.');
        }

        $resource = openssl_pkey_get_public($pem);

        if ($resource === false) {
            throw new RuntimeException('Kunci publik Passport tidak valid.');
        }

        $details = openssl_pkey_get_details($resource);

        return $this->components = [
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ];
    }

    private function base64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
