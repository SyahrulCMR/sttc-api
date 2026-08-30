<?php

use Illuminate\Http\Request;
use Sttc\SsoClient\Http\Middleware\EnsureUserHasRole;
use Symfony\Component\HttpKernel\Exception\HttpException;

function runRoleGuard(?string $activeRole, array $allowed): void
{
    $request = Request::create('/x');
    $request->setUserResolver(fn () => $activeRole === null ? null : new class($activeRole)
    {
        public function __construct(private string $role) {}

        public function getAttribute(string $key): mixed
        {
            return $key === 'active_role' ? $this->role : null;
        }
    });

    (new EnsureUserHasRole)->handle($request, fn () => response('ok'), ...$allowed);
}

it('passes when the active role is allowed', function () {
    runRoleGuard('kaprodi', ['super-admin', 'kaprodi']);
})->throwsNoExceptions();

it('forbids when the active role is not allowed', function () {
    expect(fn () => runRoleGuard('mahasiswa', ['kaprodi', 'dosen']))
        ->toThrow(HttpException::class);
});

it('forbids an unauthenticated request', function () {
    expect(fn () => runRoleGuard(null, ['kaprodi']))
        ->toThrow(HttpException::class);
});
