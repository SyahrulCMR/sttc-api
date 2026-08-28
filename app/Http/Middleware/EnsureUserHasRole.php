<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guard berbasis ROLE AKTIF (bukan sekadar kepemilikan role).
 *
 * Pemakaian: ->middleware('role:super-admin,admin-baak')
 *
 * Resolusi role aktif:
 *  1. request attribute `active_role` (di-set oleh guard token — Sprint 1a-7), atau
 *  2. session `active_role` (dipilih di role picker — Sprint 1a-9), atau
 *  3. fallback: bila user hanya punya 1 role, itulah role aktifnya.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $active = $this->resolveActiveRole($request, $user);

        if ($active === null || ! in_array($active->value, $roles, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Anda tidak memiliki hak akses untuk tindakan ini.');
        }

        return $next($request);
    }

    private function resolveActiveRole(Request $request, User $user): ?Role
    {
        $candidate = $request->attributes->get('active_role')
            ?? $this->activeRoleFromBearer($request)
            ?? ($request->hasSession() ? $request->session()->get('active_role') : null);

        if (is_string($candidate) && $user->hasRole($candidate)) {
            return Role::tryFrom($candidate);
        }

        if ($user->roles->count() === 1) {
            return Role::tryFrom($user->roles->first()->slug);
        }

        return null;
    }

    private function activeRoleFromBearer(Request $request): ?string
    {
        $token = $request->bearerToken();

        if ($token === null || substr_count($token, '.') !== 2) {
            return null;
        }

        $claims = json_decode(
            base64_decode(strtr(explode('.', $token)[1], '-_', '+/')) ?: '{}',
            true,
        ) ?: [];

        return is_string($claims['active_role'] ?? null) ? $claims['active_role'] : null;
    }
}
