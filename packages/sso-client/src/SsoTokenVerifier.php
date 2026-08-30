<?php

namespace Sttc\SsoClient;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Verifikasi access token SSO secara LOKAL — signature RS256 (JWKS) + iss + aud + exp.
 * Tidak pernah memanggil sttc-api per request (JWKS di-cache).
 */
class SsoTokenVerifier
{
    /**
     * @return array<string, mixed>
     *
     * @throws \Throwable bila token tidak valid
     */
    public function verify(string $jwt): array
    {
        $cacheKey = (string) config('sso-client.jwks_cache_key', 'sso:jwks');

        try {
            $decoded = (array) JWT::decode($jwt, JWK::parseKeySet($this->jwks($cacheKey)));
        } catch (SignatureInvalidException) {
            Cache::forget($cacheKey);
            $decoded = (array) JWT::decode($jwt, JWK::parseKeySet($this->jwks($cacheKey)));
        }

        $issuer = rtrim((string) config('sso-client.issuer'), '/');
        abort_unless(($decoded['iss'] ?? null) === $issuer, 401, 'Issuer token tidak dikenal.');
        abort_unless(
            in_array(config('sso-client.client_id'), (array) ($decoded['aud'] ?? []), true),
            401,
            'Audience token tidak cocok.',
        );

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function jwks(string $cacheKey): array
    {
        return Cache::remember(
            $cacheKey,
            now()->addSeconds((int) config('sso-client.jwks_cache_ttl', 3600)),
            fn () => Http::get(app(SsoClient::class)->jwksUrl())->throw()->json(),
        );
    }
}
