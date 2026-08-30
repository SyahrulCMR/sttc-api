<?php

namespace Sttc\SsoClient;

use Illuminate\Database\Eloquent\Model;

/**
 * Identity mirror: `users` lokal hanya menyimpan identitas (target FK / tampilan).
 * roles & active_role adalah atribut RUNTIME dari klaim — tidak di-persist.
 *
 * Model lokal ditentukan lewat `config('sso-client.user_model')` (default App\Models\User).
 */
class SsoIdentity
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public static function mirror(array $claims): Model
    {
        /** @var class-string<Model> $model */
        $model = config('sso-client.user_model', 'App\\Models\\User');

        $user = $model::updateOrCreate(
            ['identifier' => $claims['identifier'] ?? $claims['sub']],
            [
                'name' => $claims['name'] ?? 'Pengguna',
                'email' => $claims['email'] ?? ($claims['sub'].'@sso.local'),
                'status' => $claims['status'] ?? 'active',
            ],
        );

        $user->setAttribute('active_role', $claims['active_role'] ?? null);
        $user->setAttribute('roles', $claims['roles'] ?? []);

        return $user;
    }
}
