<?php

namespace Sttc\SsoClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Sttc\SsoClient\SsoClient;
use Sttc\SsoClient\SsoIdentity;
use Sttc\SsoClient\SsoTokenVerifier;

/**
 * Menjaga rute web: memastikan snapshot klaim SSO di session masih valid,
 * me-refresh bila kadaluarsa, lalu meng-mirror identitas ke `users` lokal.
 * roles/active_role TIDAK di-persist — dibaca dari klaim tiap request.
 */
class VerifySsoToken
{
    public function __construct(
        private readonly SsoClient $client,
        private readonly SsoTokenVerifier $verifier,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $claims = $this->freshClaims($request);

        if ($claims === null) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route((string) config('sso-client.redirect_route', 'sso.redirect'));
        }

        Auth::setUser(SsoIdentity::mirror($claims));
        $request->attributes->set('sso_claims', $claims);

        return $next($request);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function freshClaims(Request $request): ?array
    {
        $claims = $request->session()->get('sso_claims');

        if (is_array($claims) && ($claims['exp'] ?? 0) > time() + 5) {
            return $claims;
        }

        $refreshToken = $request->session()->get('sso_refresh_token');

        if (! $refreshToken) {
            return null;
        }

        try {
            $tokens = $this->client->refresh($refreshToken);
            $claims = $this->verifier->verify($tokens['access_token']);

            $request->session()->put([
                'sso_claims' => $claims,
                'sso_access_token' => $tokens['access_token'],
                'sso_refresh_token' => $tokens['refresh_token'],
            ]);

            return $claims;
        } catch (\Throwable) {
            $request->session()->forget(['sso_claims', 'sso_access_token', 'sso_refresh_token']);

            return null;
        }
    }
}
