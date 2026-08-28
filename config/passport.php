<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Token Time-To-Live (menit)
    |--------------------------------------------------------------------------
    |
    | Lihat epics/sprint-1-plan.md §5.1.
    | - access  : 15 menit untuk semua role (role sensitif dilindungi deny-list).
    | - refresh : 14 hari (role umum). TTL 8 jam khusus role sensitif = Sprint 1b.
    | - auth code: default Passport 10 menit (PT10M, hardcoded di package) — masih
    |   sesuai OAuth BCP, single-use. Lihat adr/0003.
    |
    */

    'ttl' => [
        'access' => (int) env('PASSPORT_ACCESS_TOKEN_TTL', 15),
        'refresh' => (int) env('PASSPORT_REFRESH_TOKEN_TTL', 60 * 24 * 14),
    ],

];
