<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\OAuthClient;
use App\Passport\AccessToken;
use App\Passport\RefreshTokenRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

/**
 * Konfigurasi minimal Laravel Passport (ADR-0002):
 * - Authorization Code + PKCE + Refresh sebagai satu-satunya grant untuk pihak ketiga.
 * - Password, Implicit, Device Code grant: DIMATIKAN.
 * - Client Credentials tetap aktif di package (tidak ada toggle), tapi tidak ada
 *   client yang didaftarkan dengan grant tersebut.
 */
class PassportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // TTL refresh per-role: pangkas jadi 8 jam untuk scope role sensitif (task 2c-1).
        $this->app->bind(
            \Laravel\Passport\Bridge\RefreshTokenRepository::class,
            RefreshTokenRepository::class,
        );
    }

    public function boot(): void
    {
        Passport::$passwordGrantEnabled = false;
        Passport::$implicitGrantEnabled = false;
        Passport::$deviceCodeGrantEnabled = false;

        Passport::useClientModel(OAuthClient::class);
        Passport::useAccessTokenEntity(AccessToken::class);

        // Scope `role:<slug>` membawa ROLE AKTIF terpilih melalui alur authorize (1a-9).
        // Validasi "user benar-benar punya role tsb" dilakukan di controller login (1a-9),
        // bukan di sini.
        Passport::tokensCan(
            collect(Role::cases())
                ->mapWithKeys(fn (Role $role) => ["role:{$role->value}" => 'Konteks: '.$role->label()])
                ->all()
        );

        Passport::tokensExpireIn(Carbon::now()->addMinutes((int) config('passport.ttl.access', 15)));
        Passport::refreshTokensExpireIn(Carbon::now()->addMinutes((int) config('passport.ttl.refresh', 60 * 24 * 14)));
        Passport::personalAccessTokensExpireIn(Carbon::now()->addMonths(6));

        // Semua client internal first-party -> layar consent praktis tak pernah tampil,
        // tapi binding response-nya tetap wajib ada. View di-perhalus pada 1a-9.
        Passport::authorizationView('oauth.authorize');

        // Custom JWT claims (active_role, roles, status) di-wire pada task 1a-7.
    }
}
