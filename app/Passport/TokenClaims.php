<?php

namespace App\Passport;

use App\Models\User;

/**
 * Menyediakan data custom claim untuk access token (ADR-0003).
 */
class TokenClaims
{
    /**
     * @return array{roles: list<string>, status: string|null}
     */
    public function forUser(string|int|null $userIdentifier): array
    {
        if ($userIdentifier === null) {
            return ['roles' => [], 'status' => null];
        }

        /** @var User|null $user */
        $user = User::query()->with('roles:id,slug')->find($userIdentifier);

        if ($user === null) {
            return ['roles' => [], 'status' => null];
        }

        return [
            'roles' => $user->roles->pluck('slug')->values()->all(),
            'status' => $user->status?->value,
        ];
    }
}
