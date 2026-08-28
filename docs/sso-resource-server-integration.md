# Integrasi Resource Server (sttc-siakad / sttc-website)

Referensi **copy-paste** untuk menjadikan sebuah aplikasi Laravel sebagai OAuth2
client + resource server terhadap `sttc-api` (Authorization Server / Passport).

Verifikasi token **selalu lokal** (JWKS) — tidak ada panggilan ke `sttc-api` per request
(ADR-0002, ADR-0003). File di dua repo harus identik; beri header `// SYNCED — jangan edit sepihak`.

> Ekstraksi ke package `sttc/sso-client` = task penutup Epic 1.

---

## 1. Dependency

```bash
composer require firebase/php-jwt
```

(`firebase/php-jwt` menyediakan `JWK::parseKeySet()` untuk verifikasi dari JWKS.)

---

## 2. Konfigurasi (`config/services.php`)

```php
'sso' => [
    'base_url'      => env('SSO_BASE_URL', 'http://localhost:8000'),
    'client_id'     => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect_uri'  => env('SSO_REDIRECT_URI'),
    'jwks_url'      => env('SSO_JWKS_URL', env('SSO_BASE_URL').'/oauth/jwks'),
    'authorize_url' => env('SSO_BASE_URL').'/oauth/authorize',
    'token_url'     => env('SSO_BASE_URL').'/oauth/token',
    // Untuk back-channel single-logout (skema lama, dipertahankan):
    'app_name'      => env('SSO_APP_NAME'),      // 'siakad' | 'blog'
    'app_secret'    => env('SSO_APP_SECRET'),
],
```

`.env` (contoh `sttc-siakad`, sesuai `PassportClientSeeder` lokal):

```
SSO_BASE_URL=http://localhost:8000
SSO_CLIENT_ID=01999999-0000-7000-8000-0000000000a1
SSO_CLIENT_SECRET=sttc-siakad-local-dev-secret
SSO_REDIRECT_URI=http://localhost:8001/sso/callback
```

---

## 3. `app/Services/SsoClient.php` — alur Authorization Code + PKCE

```php
<?php

namespace App\Services;

use GuzzleHttp\Client;                 // atau Illuminate\Support\Facades\Http
use Illuminate\Support\Str;

class SsoClient
{
    /** URL untuk memulai login; simpan verifier + state di session. */
    public function authorizationUrl(string $state, string $verifier): string
    {
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return config('services.sso.authorize_url').'?'.http_build_query([
            'client_id'             => config('services.sso.client_id'),
            'redirect_uri'          => config('services.sso.redirect_uri'),
            'response_type'         => 'code',
            'state'                 => $state,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    /** Tukar authorization code -> token. */
    public function exchange(string $code, string $verifier): array
    {
        return \Illuminate\Support\Facades\Http::asForm()
            ->post(config('services.sso.token_url'), [
                'grant_type'    => 'authorization_code',
                'client_id'     => config('services.sso.client_id'),
                'client_secret' => config('services.sso.client_secret'),
                'redirect_uri'  => config('services.sso.redirect_uri'),
                'code'          => $code,
                'code_verifier' => $verifier,
            ])->throw()->json();
    }

    public function refresh(string $refreshToken): array
    {
        return \Illuminate\Support\Facades\Http::asForm()
            ->post(config('services.sso.token_url'), [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id'     => config('services.sso.client_id'),
                'client_secret' => config('services.sso.client_secret'),
            ])->throw()->json();
    }
}
```

---

## 4. `app/Support/SsoTokenVerifier.php` — verifikasi LOKAL (JWKS)

```php
<?php

namespace App\Support;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SsoTokenVerifier
{
    /**
     * @return array<string,mixed> klaim token bila valid
     *
     * @throws \Throwable bila signature / iss / aud / exp tidak valid
     */
    public function verify(string $jwt): array
    {
        $keys = Cache::remember('sso:jwks', now()->addHour(), function () {
            return Http::get(config('services.sso.jwks_url'))->throw()->json();
        });

        try {
            $decoded = (array) JWT::decode($jwt, JWK::parseKeySet($keys));
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            // kemungkinan rotasi kunci -> refresh JWKS sekali lalu ulangi
            Cache::forget('sso:jwks');
            $keys = Http::get(config('services.sso.jwks_url'))->throw()->json();
            $decoded = (array) JWT::decode($jwt, JWK::parseKeySet($keys));
        }

        abort_unless($decoded['iss'] === rtrim(config('services.sso.base_url'), '/'), 401);
        abort_unless(in_array(config('services.sso.client_id'), (array) $decoded['aud'], true), 401);

        return $decoded;
    }
}
```

---

## 5. `app/Http/Middleware/VerifySsoToken.php`

Dua mode: **API** (Bearer token, stateless) & **web** (snapshot klaim di session).

```php
<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\SsoTokenVerifier;
use Closure;
use Illuminate\Http\Request;

class VerifySsoToken
{
    public function __construct(private SsoTokenVerifier $verifier) {}

    public function handle(Request $request, Closure $next)
    {
        // --- Mode API (Bearer) ---
        if ($bearer = $request->bearerToken()) {
            $claims = $this->verifier->verify($bearer);   // throws bila invalid
            $request->setUserResolver(fn () => $this->mirror($claims));
            $request->attributes->set('sso_claims', $claims);

            return $next($request);
        }

        // --- Mode web (session) ---
        $claims = $request->session()->get('sso_claims');

        if (! $claims || ($claims['exp'] ?? 0) < time()) {
            return redirect()->route('sso.redirect');   // mulai (silent) re-authorize
        }

        $request->setUserResolver(fn () => $this->mirror($claims));
        $request->attributes->set('sso_claims', $claims);

        return $next($request);
    }

    /**
     * Identity mirror: `users` lokal HANYA menyimpan identitas (target FK).
     * roles/active_role TIDAK di-persist — selalu dari klaim (§5.4).
     */
    private function mirror(array $claims): User
    {
        $user = User::updateOrCreate(
            ['identifier' => $claims['identifier'] ?? $claims['sub']],
            [
                'name'   => $claims['name']   ?? 'Pengguna',
                'email'  => $claims['email']  ?? null,
                'status' => $claims['status'] ?? 'active',
            ]
        );

        // atribut runtime (tidak disimpan)
        $user->setAttribute('active_role', $claims['active_role'] ?? null);
        $user->setAttribute('roles', $claims['roles'] ?? []);

        return $user;
    }
}
```

> **Catatan klaim:** Sprint 1 access token membawa `sub`, `active_role`, `roles`, `status`.
> `identifier` / `name` / `email` **belum** ada di token — sementara resource server
> memanggil satu kali `GET {sso}/api/user` saat pertama mirror, ATAU field ini ditambahkan
> ke klaim di `App\Passport\TokenClaims` (keputusan awal 1b-8).

---

## 6. `app/Http/Middleware/EnsureUserHasRole.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $active = $request->user()?->getAttribute('active_role');

        abort_unless($active !== null && in_array($active, $roles, true), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
```

Daftarkan alias `role` di `bootstrap/app.php`, lalu:

```php
Route::middleware(['web', 'sso', 'role:kaprodi,dosen,admin-baak'])->group(function () {
    // modul akademik
});
```

---

## 7. Callback & perpindahan konteks role

```php
// routes/web.php
Route::get('/sso/redirect', function () {
    $state = Str::random(40);
    $verifier = Str::random(80);
    session(['sso_state' => $state, 'sso_verifier' => $verifier]);

    return redirect(app(App\Services\SsoClient::class)->authorizationUrl($state, $verifier));
})->name('sso.redirect');

Route::get('/sso/callback', function (Request $request) {
    abort_unless($request->query('state') === session('sso_state'), 400);

    $tokens = app(App\Services\SsoClient::class)
        ->exchange($request->query('code'), session('sso_verifier'));

    $claims = app(App\Support\SsoTokenVerifier::class)->verify($tokens['access_token']);

    session([
        'sso_claims'        => $claims,
        'sso_access_token'  => $tokens['access_token'],
        'sso_refresh_token' => $tokens['refresh_token'],
    ]);
    session()->forget(['sso_state', 'sso_verifier']);

    return redirect()->intended('/');
});

// Perpindahan konteks role (silent re-authorize di sttc-api)
Route::get('/switch-context', function (Request $request) {
    $back = url('/sso/redirect');   // akan memicu authorize baru
    return redirect(config('services.sso.base_url').'/switch-role?'.http_build_query([
        'role'     => $request->query('role'),
        'redirect' => config('services.sso.authorize_url').'?'.http_build_query([
            'client_id'             => config('services.sso.client_id'),
            'redirect_uri'          => config('services.sso.redirect_uri'),
            'response_type'         => 'code',
            'state'                 => session('sso_state', ''),
            'code_challenge'        => '', // isi bila memulai verifier baru
            'code_challenge_method' => 'S256',
        ]),
    ]));
})->middleware('sso');
```

---

## 8. Single-logout (back-channel — skema lama dipertahankan)

`sttc-api` `broadcastLogout` memanggil webhook `{app}/api/sso/force-logout` dengan
`secret` + `local_session_id`. Resource server:

```php
Route::post('/api/sso/force-logout', function (Request $request) {
    abort_unless(hash_equals(config('services.sso.app_secret'), $request->input('secret')), 403);

    // hapus session lokal berdasarkan local_session_id
    // (Illuminate\Session\DatabaseSessionHandler::destroy($id))
    return response()->json(['ok' => true]);
});
```

Saat user logout dari resource server: panggil `{sso}/api/sso/logout` (skema lama) +
hapus session lokal.

---

## 9. Deny-list revocation (role sensitif saja)

Resource server dengan modul untuk `super-admin` / `admin-keuangan` boleh menambah cek
deny-list (Redis bersama, 1 VPS). Salin `App\Support\TokenDenyList` + `EnsureTokenNotRevoked`
dari `sttc-api`. Role umum **tidak** dicek.

---

## 10. Checklist adopsi

- [ ] `composer require firebase/php-jwt`
- [ ] `config/services.php` blok `sso` + `.env`
- [ ] `SsoClient`, `SsoTokenVerifier`, `VerifySsoToken`, `EnsureUserHasRole` (identik dgn dokumen ini)
- [ ] alias middleware `sso` + `role` di `bootstrap/app.php`
- [ ] route `/sso/redirect`, `/sso/callback`, `/api/sso/force-logout`
- [ ] migration `users`: `identifier` unik, `status`, tanpa kolom role (mirror-only)
- [ ] `route('login')` app lama diarahkan ke `route('sso.redirect')`
- [ ] test: token dummy invalid → 401 tanpa HTTP call ke sttc-api (`Http::fake` tidak terpanggil)
