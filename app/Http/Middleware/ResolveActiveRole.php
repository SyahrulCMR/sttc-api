<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menentukan ROLE AKTIF sebelum `/oauth/authorize` diproses Passport:
 *
 *  - user belum login          -> teruskan (Passport redirect ke /login)
 *  - user tanpa role            -> 403
 *  - session `active_role` valid -> injeksi scope `role:<slug>`
 *  - user 1 role                -> set otomatis + injeksi scope
 *  - user >1 role, belum pilih  -> redirect ke role picker
 *
 * Di-append ke grup middleware `web`; hanya bertindak pada route authorize.
 */
class ResolveActiveRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('passport.authorizations.authorize')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $roles = $user->roles->pluck('slug');

        if ($roles->isEmpty()) {
            abort(Response::HTTP_FORBIDDEN, 'Akun Anda belum memiliki role. Hubungi administrator.');
        }

        $active = $request->session()->get('active_role');

        if (! is_string($active) || ! $roles->contains($active)) {
            $active = null;
        }

        if ($active === null && $roles->count() === 1) {
            $active = $roles->first();
            $request->session()->put('active_role', $active);
        }

        if ($active === null) {
            $request->session()->put('role_picker_return', $request->fullUrl());

            return redirect()->route('role.select');
        }

        $scope = collect(explode(' ', (string) $request->query('scope', '')))
            ->reject(fn (string $s) => $s === '' || str_starts_with($s, 'role:'))
            ->push('role:'.$active)
            ->implode(' ');

        $request->query->set('scope', $scope);
        $request->merge(['scope' => $scope]);

        return $next($request);
    }
}
