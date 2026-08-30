<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi back-channel SSO (single-logout server-to-server)
|--------------------------------------------------------------------------
|
| Otentikasi memakai Laravel Passport (oauth_clients) — lihat adr/0002.
| File ini HANYA untuk kanal back-channel `SsoBackChannelController`
| (registerSession + broadcastLogout) yang dipakai `SsoSession` untuk SLO.
| Alur opaque token lama (SsoToken / /sso/login / /sso/verify) sudah dihapus (task 2b-1).
|
| Kunci `apps.*` = client_id OAuth (sama dengan yang dikirim resource server pada
| field `app`). `secret` = shared secret back-channel yang dipegang RS sebagai
| `SSO_APP_SECRET`. `logout_webhook` = endpoint force-logout milik RS.
|
*/

return [

    'apps' => [

        'sttc-siakad' => [
            'logout_webhook' => env('SIAKAD_LOGOUT_WEBHOOK', rtrim((string) env('SIAKAD_URL', 'http://127.0.0.1:8001'), '/').'/sso/force-logout'),
            'secret' => env('SIAKAD_BACKCHANNEL_SECRET'),
        ],

        'sttc-website' => [
            'logout_webhook' => env('WEBSITE_LOGOUT_WEBHOOK', rtrim((string) env('WEBSITE_URL', 'http://127.0.0.1:8002'), '/').'/sso/force-logout'),
            'secret' => env('WEBSITE_BACKCHANNEL_SECRET'),
        ],

    ],

];
