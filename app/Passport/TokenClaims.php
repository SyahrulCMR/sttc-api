<?php

namespace App\Passport;

use App\Models\User;

/**
 * Menyediakan data custom claim untuk access token (ADR-0003).
 */
class TokenClaims
{
    /**
     * @return array{identifier: string|null, name: string|null, email: string|null, roles: list<string>, status: string|null}
     */
    public function forUser(string|int|null $userIdentifier): array
    {
        $empty = ['identifier' => null, 'name' => null, 'email' => null, 'roles' => [], 'status' => null];

        if ($userIdentifier === null) {
            return $empty;
        }

        /** @var User|null $user */
        $user = User::query()->with('roles:id,slug')->find($userIdentifier);

        if ($user === null) {
            return $empty;
        }

        return [
            'identifier' => $user->identifier,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('slug')->values()->all(),
            'status' => $user->status?->value,
        ];
    }
}
