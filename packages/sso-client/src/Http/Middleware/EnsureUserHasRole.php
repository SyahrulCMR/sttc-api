<?php

namespace Sttc\SsoClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guard rute berdasarkan `active_role` dari klaim token (bukan kolom `role` lokal).
 * Dipakai setelah middleware `sso` (VerifySsoToken) mengeset user + atribut runtime.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $active = $request->user()?->getAttribute('active_role');

        abort_unless(
            is_string($active) && in_array($active, $roles, true),
            Response::HTTP_FORBIDDEN,
            'Anda tidak memiliki hak akses untuk halaman ini.',
        );

        return $next($request);
    }
}
