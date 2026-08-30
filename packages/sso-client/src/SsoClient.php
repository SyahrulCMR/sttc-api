<?php

namespace Sttc\SsoClient;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Client OAuth2 (Authorization Code + PKCE) terhadap sttc-api, plus kanal
 * back-channel (register-session / broadcast-logout).
 */
class SsoClient
{
    private function base(): string
    {
        return rtrim((string) config('sso-client.server_url'), '/');
    }

    /**
     * URL untuk memulai login. Simpan $state & $verifier di session pemanggil.
     */
    public function authorizationUrl(string $state, string $verifier): string
    {
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return $this->base().'/oauth/authorize?'.http_build_query([
            'client_id' => config('sso-client.client_id'),
            'redirect_uri' => config('sso-client.redirect_uri'),
            'response_type' => 'code',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public static function newVerifier(): string
    {
        return Str::random(80);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    public function exchange(string $code, string $verifier): array
    {
        return Http::asForm()->post($this->base().'/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('sso-client.client_id'),
            'client_secret' => config('sso-client.client_secret'),
            'redirect_uri' => config('sso-client.redirect_uri'),
            'code' => $code,
            'code_verifier' => $verifier,
        ])->throw()->json();
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}
     */
    public function refresh(string $refreshToken): array
    {
        return Http::asForm()->post($this->base().'/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => config('sso-client.client_id'),
            'client_secret' => config('sso-client.client_secret'),
        ])->throw()->json();
    }

    public function jwksUrl(): string
    {
        return $this->base().'/oauth/jwks';
    }

    /**
     * Daftarkan sesi lokal RS ke sttc-api agar bisa di-single-logout (front-channel).
     * Best-effort: kegagalan tidak boleh menggagalkan callback login.
     */
    public function registerSession(string $userIdentifier, string $localSessionId): void
    {
        try {
            Http::asForm()->post($this->base().'/api/sso/register-session', [
                'app' => config('sso-client.backchannel.app'),
                'secret' => config('sso-client.backchannel.secret'),
                'user_identifier' => $userIdentifier,
                'local_session_id' => $localSessionId,
            ]);
        } catch (\Throwable) {
            // diamkan — SLO front-channel adalah best-effort di atas kegagalan refresh.
        }
    }

    /**
     * Beri tahu sttc-api bahwa user logout → cabut token + broadcast force-logout.
     * Best-effort: jangan gagalkan logout lokal hanya karena SSO server tak terjangkau.
     */
    public function broadcastLogout(string $userIdentifier): void
    {
        try {
            Http::asForm()->post($this->base().'/api/sso/logout', [
                'app' => config('sso-client.backchannel.app'),
                'secret' => config('sso-client.backchannel.secret'),
                'user_identifier' => $userIdentifier,
            ]);
        } catch (\Throwable) {
            // diamkan
        }
    }
}
