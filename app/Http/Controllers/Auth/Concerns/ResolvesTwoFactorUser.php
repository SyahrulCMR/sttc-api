<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ResolvesTwoFactorUser
{
    /**
     * User yang sedang menjalani step-up 2FA: pending dari alur login,
     * atau user yang sudah terautentikasi (self-service).
     */
    protected function twoFactorUser(Request $request): User
    {
        $pendingId = $request->session()->get('pending_2fa_user_id');

        $user = $pendingId
            ? User::find($pendingId)
            : $request->user();

        if (! $user instanceof User) {
            throw new HttpException(419, 'Sesi 2FA tidak ditemukan. Silakan masuk kembali.');
        }

        return $user;
    }

    protected function isLoginFlow(Request $request): bool
    {
        return $request->session()->has('pending_2fa_user_id') && $request->user() === null;
    }
}
