<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Support\TokenDenyList;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hybrid revocation check untuk token dengan `active_role` sensitif
 * (super-admin / admin-keuangan). Role umum tidak dicek (fail-open by design).
 *
 * Dipasang SESUDAH `auth:api` — token sudah diverifikasi Passport; middleware
 * ini hanya membaca payload untuk cek deny-list.
 */
class EnsureTokenNotRevoked
{
    public function __construct(private readonly TokenDenyList $denyList) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $request->user() === null) {
            return $next($request);
        }

        $claims = $this->claims($token);
        $activeRole = $claims['active_role'] ?? null;

        if (! $this->isSensitive($activeRole)) {
            return $next($request);
        }

        $jti = (string) ($claims['jti'] ?? '');
        $issuedAt = (int) ($claims['iat'] ?? 0);
        $subject = $claims['sub'] ?? $request->user()->getAuthIdentifier();

        if ($this->denyList->isTokenRevoked($jti)
            || $this->denyList->isUserRevokedSince($subject, $issuedAt)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Sesi Anda telah dicabut. Silakan masuk kembali.');
        }

        return $next($request);
    }

    private function isSensitive(mixed $slug): bool
    {
        return is_string($slug)
            && collect(Role::sensitive())->contains(fn (Role $role) => $role->value === $slug);
    }

    /**
     * @return array<string, mixed>
     */
    private function claims(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return [];
        }

        return json_decode(base64_decode(strtr($parts[1], '-_', '+/')) ?: '{}', true) ?: [];
    }
}
