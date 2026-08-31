# Integrasi Resource Server (sttc-siakad / sttc-website)

Menjadikan aplikasi Laravel sebagai OAuth2 client + resource server terhadap `sttc-api`
(Authorization Server / Passport). Verifikasi token **selalu lokal** (JWKS) — tidak ada
panggilan ke `sttc-api` per request (ADR-0002, ADR-0003).

> **Sprint 2:** kode RS yang dulu di-copy-paste (`SsoClient`, `SsoTokenVerifier`, `SsoIdentity`,
> `VerifySsoToken`, `EnsureUserHasRole`) sudah diekstrak ke paket Composer **`sttc/sso-client`**
> (`sttc-api/packages/sso-client/`). Detail pemasangan & konfigurasi: lihat `README.md` paket.

---

## 1. Pasang paket

`composer.json` aplikasi konsumen:

```jsonc
"repositories": [
    { "type": "path", "url": "../sttc-api/packages/sso-client", "options": { "symlink": true } }
],
"require": { "sttc/sso-client": "@dev" }
```

```bash
composer update sttc/sso-client
php artisan vendor:publish --tag=sso-client-config
```

Alias middleware di `bootstrap/app.php` (eksplisit):

```php
$middleware->alias([
    'sso'  => \Sttc\SsoClient\Http\Middleware\VerifySsoToken::class,
    'role' => \Sttc\SsoClient\Http\Middleware\EnsureUserHasRole::class,
]);
```

---

## 2. Environment (`.env`) — kanonik

| var | contoh (dev, `sttc-siakad`) | keterangan |
|---|---|---|
| `SSO_SERVER_URL` | `http://127.0.0.1:8000` | alamat jaringan sttc-api (`/oauth/*`, `/api/sso/*`) |
| `SSO_ISSUER` | *(kosong)* | klaim `iss` yg diharapkan; default = `SSO_SERVER_URL`. Set terpisah bila `app.url` sttc-api ≠ alamat jaringan |
| `SSO_CLIENT_ID` | `01999999-0000-7000-8000-0000000000a1` | id client OAuth (UUID, dari `oauth_clients`) |
| `SSO_CLIENT_SECRET` | `sttc-siakad-local-dev-secret` | secret client (plaintext) |
| `SSO_REDIRECT_URI` | `http://localhost:8001/sso/callback` | **harus sama persis** dengan yg terdaftar di `oauth_clients` |
| `SSO_APP_NAME` | `sttc-siakad` | identitas app di kanal back-channel = **client_id string** (kunci `config/sso.php` di sttc-api) |
| `SSO_APP_SECRET` | `sttc-siakad-backchannel-dev` | shared secret back-channel; = `SIAKAD_BACKCHANNEL_SECRET` di sttc-api |

Sisi `sttc-api` (`config/sso.php` + `.env`): `SIAKAD_URL` / `WEBSITE_URL` (base URL RS, webhook =
`{URL}/sso/force-logout`) + `SIAKAD_BACKCHANNEL_SECRET` / `WEBSITE_BACKCHANNEL_SECRET`
(harus cocok dengan `SSO_APP_SECRET` RS terkait).

> Gunakan `127.0.0.1` (bukan `localhost`) untuk URL server-to-server di dev — `php artisan serve`
> mengikat ke `127.0.0.1` dan `localhost` bisa gagal resolve ke `::1` di Windows.
>
> **Browser vs server-to-server (BUG-0007):** cookie session di-scope per-hostname —
> `127.0.0.1` dan `localhost` dianggap domain berbeda. Selalu buka RS di browser lewat
> hostname yang **sama persis** dengan host di `SSO_REDIRECT_URI`, kalau tidak `state`/PKCE
> verifier yang disimpan saat `/auth/redirect` tak akan ketemu saat callback → `400 Sesi SSO
> tidak ditemukan`. URL server-to-server (`SSO_SERVER_URL`, `SIAKAD_URL`, `WEBSITE_URL`) boleh
> tetap `127.0.0.1`; yang penting host redirect-browser konsisten dengan yang didaftarkan.

---

## 3. Routes RS

```php
// web.php
Route::get('/auth/redirect', [OAuthClientController::class, 'redirect'])->name('sso.redirect');
Route::get('/sso/callback',  [OAuthClientController::class, 'callback'])->name('sso.callback');
Route::match(['get','post'], '/logout', [LogoutController::class, 'logout'])->name('logout');

// webhook force-logout dari sttc-api — WAJIB di web.php dgn path `/sso/force-logout`
// (TANPA prefix /api) + CSRF-except 'sso/force-logout'. Ini path yang dibangun
// sttc-api/config/sso.php untuk SEMUA app. Menaruhnya di api.php = 404 senyap (BUG-0002).
Route::post('/sso/force-logout', [SsoWebhookController::class, 'forceLogout'])->middleware('throttle:30,1');

// modul terproteksi
Route::middleware(['sso', 'role:super-admin,admin-baak,kaprodi,dosen'])->group(fn () => /* ... */);
```

`OAuthClientController::callback` **wajib** memanggil `SsoClient::registerSession($identifier,
$request->session()->getId())` setelah `SsoIdentity::mirror()` — supaya single-logout front-channel
berfungsi. `LogoutController` memanggil `SsoClient::broadcastLogout($identifier)`.

---

## 4. Model `users` = identity mirror

`SsoIdentity::mirror()` melakukan `updateOrCreate(['identifier' => ...], ['name','email','status'])`.
Kolom `role` lokal **tidak dipakai** — `roles` & `active_role` adalah atribut runtime dari klaim
token (`$user->getAttribute('active_role')`). Migrasi `users`: `identifier` unique, `status` +
nilai `inactive`, `password` nullable, index `lower(email)`.

---

## 5. Perilaku token yang perlu diketahui RS

- **Access token TTL 15 menit.** `VerifySsoToken` otomatis me-refresh via `SsoClient::refresh()`
  saat klaim `exp` lewat; kegagalan refresh → redirect ke `sso.redirect`.
- **Refresh TTL: 14 hari (role umum) / 8 jam (role sensitif: super-admin, admin-keuangan).**
  Ditegakkan di sttc-api (task 2c-1) — **tidak ada perubahan kode RS**. Konsekuensi: sesi RS
  untuk user ber-`active_role` sensitif "mati" maksimal 8 jam → user harus login ulang (+ 2FA).
- **Rotasi refresh:** tiap refresh menghasilkan pasangan token baru; token lama langsung dicabut.
  Replay refresh token yang sudah dicabut → sttc-api mencabut **seluruh** token user (deny-list).
- **JWKS di-cache** `SSO_JWKS_CACHE_TTL` detik (default 3600). `SsoTokenVerifier` otomatis
  `Cache::forget` + retry sekali saat `SignatureInvalidException` (rotasi kunci).

---

## 6. Verifikasi integrasi

`php artisan test` di RS harus hijau. Untuk uji end-to-end lintas aplikasi, lihat
`epics/sprint-2-plan.md` §10 (skrip harness di scratchpad: `flow.php`, `e2e2.php`, `slo.php`).
