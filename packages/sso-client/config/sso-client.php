<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alamat sttc-api (Authorization Server)
    |--------------------------------------------------------------------------
    |
    | `server_url` : alamat jaringan yang dipakai untuk memanggil endpoint OAuth
    |                (/oauth/authorize, /oauth/token, /oauth/jwks).
    | `issuer`     : nilai klaim `iss` yang diharapkan di dalam token. Di produksi
    |                identik dengan `server_url`; dipisah untuk lingkungan lokal
    |                di mana app.url sttc-api (mis. http://localhost:8000) berbeda
    |                dari cara RS menjangkaunya. Default: sama dengan `server_url`.
    |
    */

    'server_url' => env('SSO_SERVER_URL'),
    'issuer' => env('SSO_ISSUER', env('SSO_SERVER_URL')),

    /*
    |--------------------------------------------------------------------------
    | Kredensial client OAuth (confidential, Authorization Code + PKCE)
    |--------------------------------------------------------------------------
    */

    'client_id' => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect_uri' => env('SSO_REDIRECT_URI'),

    /*
    |--------------------------------------------------------------------------
    | Integrasi aplikasi
    |--------------------------------------------------------------------------
    |
    | `user_model`     : model Eloquent lokal untuk identity mirror.
    | `redirect_route` : route bernama yang memulai alur login (redirect ke authorize).
    |
    */

    'user_model' => env('SSO_USER_MODEL', 'App\\Models\\User'),
    'redirect_route' => env('SSO_REDIRECT_ROUTE', 'sso.redirect'),

    /*
    |--------------------------------------------------------------------------
    | Back-channel (single-logout server-to-server)
    |--------------------------------------------------------------------------
    |
    | Dipakai untuk mendaftarkan sesi lokal ke sttc-api (register-session) dan
    | menerima webhook force-logout. `app` = identitas aplikasi yang dikenal
    | sttc-api (config/sso.php), `secret` = shared secret back-channel.
    |
    */

    'backchannel' => [
        'app' => env('SSO_APP_NAME'),
        'secret' => env('SSO_APP_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache JWKS
    |--------------------------------------------------------------------------
    |
    | Kunci publik sttc-api di-cache supaya verifikasi token tidak memanggil
    | jaringan tiap request. TTL dalam detik.
    |
    */

    'jwks_cache_key' => env('SSO_JWKS_CACHE_KEY', 'sso:jwks'),
    'jwks_cache_ttl' => (int) env('SSO_JWKS_CACHE_TTL', 3600),

];
