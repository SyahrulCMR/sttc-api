# OAuth Clients (Laravel Passport)

`sttc-api` = OAuth2 Authorization Server. Hanya **2 client internal** (first-party,
confidential, Authorization Code + PKCE). Tidak ada onboarding self-service (ADR-0002).

| Client | Dipakai oleh | Grant | redirect_uri (lokal) |
|---|---|---|---|
| `sttc-siakad` | SIAKAD (resource server) | `authorization_code`, `refresh_token` | `http://localhost:8001/sso/callback` |
| `sttc-website` | Website/CMS (resource server) | `authorization_code`, `refresh_token` | `http://localhost:8002/auth/callback` |

Semua client **first-party** (`owner` NULL) → `OAuthClient::skipsAuthorization()` true →
layar consent dilewati.

## Lokal / Dev

`php artisan db:seed` menjalankan `PassportClientSeeder` — id & secret **deterministik**
dari `.env` (`SIAKAD_CLIENT_SECRET`, `SIAKAD_REDIRECT_URI`, `WEBSITE_CLIENT_SECRET`,
`WEBSITE_REDIRECT_URI`; redirect boleh multi-value dipisah koma). Aman di-seed ulang.

id tetap:
- `sttc-siakad` → `01999999-0000-7000-8000-0000000000a1`
- `sttc-website` → `01999999-0000-7000-8000-0000000000a2`

Resource server memakai `client_id` + `client_secret` + `redirect_uri` yang sama di
`.env` masing-masing repo.

## Staging / Produksi

**JANGAN** jalankan `PassportClientSeeder` (otomatis dilewati bila `APP_ENV=production`).
Daftarkan manual:

```bash
php artisan passport:client \
  --name="sttc-siakad" \
  --redirect_uri="https://siakad.stt-cipasung.ac.id/sso/callback"
```

Output `Client ID` + `Client secret` muncul **sekali** — simpan ke secret manager, isikan
ke `.env` `sttc-siakad`. Ulangi untuk `sttc-website`.

Menambah redirect_uri client yang sudah ada: `php artisan passport:client` tidak
meng-update; ubah baris `oauth_clients.redirect_uris` (JSON array) langsung via tinker
atau migration data.

## Rotasi secret

`php artisan passport:client` baru untuk client tsb, update `.env` resource server,
`revoked=true` untuk client lama setelah cutover.
