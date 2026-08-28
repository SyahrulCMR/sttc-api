<?php

/*
|--------------------------------------------------------------------------
| DEPRECATED — SSO lama (opaque token + secret statis per app)
|--------------------------------------------------------------------------
|
| Digantikan bertahap oleh Laravel Passport (oauth_clients) sejak Sprint 1
| Epic 1 — lihat adr/0002 & epics/sprint-1-plan.md.
|
| MASIH DIPAKAI (koeksistensi): SsoAuthController::verifyToken / registerSession
| / broadcastLogout. `SsoSession` + `broadcastLogout` akan dipertahankan permanen
| sebagai back-channel logout; sisanya dihapus setelah Passport terbukti stabil.
|
*/

return [
    'apps' => [
        'siakad' => [
            'redirect_url' => env('SIAKAD_URL').'/sso/callback',
            'logout_webhook' => env('SIAKAD_URL').'/api/sso/force-logout',
            'secret' => env('SIAKAD_SECRET'),
        ],
        'lms' => [
            'redirect_url' => env('LMS_URL').'/sso/callback',
            'logout_webhook' => env('LMS_URL').'/api/sso/force-logout',
            'secret' => env('LMS_SECRET'),
        ],
        'blog' => [
            'redirect_url' => env('BLOG_URL').'/sso/callback',
            'logout_webhook' => env('BLOG_URL').'/api/sso/force-logout',
            'secret' => env('BLOG_SECRET'),
        ],
    ],
    'token_ttl' => 30,
];
