# sttc/sso-client

Klien SSO bersama untuk resource server ekosistem STTC (`sttc-siakad`, `sttc-website`).
Berisi kode yang sebelumnya di-copy-paste antar repo (rencana Sprint 1 §9, diekstrak Sprint 2 task 2a).

## Isi

| Kelas | Peran |
|---|---|
| `Sttc\SsoClient\SsoClient` | Alur OAuth2 Authorization Code + PKCE (authorize/exchange/refresh) + back-channel (register-session, broadcast-logout) |
| `Sttc\SsoClient\SsoTokenVerifier` | Verifikasi access token **lokal**: RS256 via JWKS (di-cache) + cek `iss` + `aud` |
| `Sttc\SsoClient\SsoIdentity` | Identity mirror: `updateOrCreate` user lokal by `identifier`; `roles`/`active_role` = atribut runtime (tak di-persist) |
| `Sttc\SsoClient\Http\Middleware\VerifySsoToken` | Middleware sesi web: pastikan klaim valid, auto-refresh, mirror identitas |
| `Sttc\SsoClient\Http\Middleware\EnsureUserHasRole` | Guard rute berdasarkan `active_role` dari klaim |

## Pemasangan (path repository)

Paket ini tinggal di dalam repo `sttc-api` (`packages/sso-client/`). Ketiga repo diasumsikan
di-checkout sebagai sibling di bawah satu parent. Di `composer.json` aplikasi konsumen:

```jsonc
"repositories": [
    { "type": "path", "url": "../sttc-api/packages/sso-client", "options": { "symlink": true } }
],
"require": {
    "sttc/sso-client": "@dev"
}
```

> Windows tanpa Developer Mode: ganti `"symlink": true` → `false` (copy saat install; jalankan
> `composer update sttc/sso-client` tiap kali paket berubah).

Lalu:

```bash
composer update sttc/sso-client
php artisan vendor:publish --tag=sso-client-config   # -> config/sso-client.php
```

Daftarkan alias middleware di `bootstrap/app.php` aplikasi (eksplisit, tidak otomatis):

```php
$middleware->alias([
    'sso'  => \Sttc\SsoClient\Http\Middleware\VerifySsoToken::class,
    'role' => \Sttc\SsoClient\Http\Middleware\EnsureUserHasRole::class,
]);
```

## Konfigurasi (`config/sso-client.php` / env)

| env | Wajib | Keterangan |
|---|---|---|
| `SSO_SERVER_URL` | ya | Alamat jaringan sttc-api (`/oauth/*`, `/api/sso/*`) |
| `SSO_ISSUER` | tidak | Nilai `iss` yang diharapkan di token. Default = `SSO_SERVER_URL`. Set terpisah bila `app.url` sttc-api ≠ alamat jaringan (mis. lokal `http://localhost:8000` vs prod) |
| `SSO_CLIENT_ID` / `SSO_CLIENT_SECRET` | ya | Kredensial client OAuth confidential |
| `SSO_REDIRECT_URI` | ya | Harus sama persis dengan yang terdaftar di `oauth_clients` |
| `SSO_USER_MODEL` | tidak | Default `App\Models\User` |
| `SSO_REDIRECT_ROUTE` | tidak | Route bernama untuk memulai login. Default `sso.redirect` |
| `SSO_APP_NAME` / `SSO_APP_SECRET` | ya (SLO) | Identitas + shared secret back-channel (dikenal `sttc-api` `config/sso.php`) |
| `SSO_JWKS_CACHE_TTL` | tidak | Detik. Default 3600 |

## Test

```bash
composer install
vendor/bin/pest
```
