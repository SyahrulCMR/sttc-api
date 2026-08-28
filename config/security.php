<?php

/*
|--------------------------------------------------------------------------
| Kebijakan keamanan login — epics/sprint-1-plan.md §5.2
|--------------------------------------------------------------------------
|
| Throttle/lockout brute-force login berlapis. Semua counter di store `throttle`
| (Redis). Pesan gagal auth generik & identik; dummy Hash::check saat user tidak
| ditemukan (anti user-enumeration via timing).
|
*/

return [
    // Store cache untuk counter. Di test di-override ke `array`.
    'store' => env('LOGIN_THROTTLE_STORE', 'throttle'),

    // Bila store tidak bisa dihubungi (Redis down) -> jangan blokir login (fail-open) + log.
    'fail_open' => (bool) env('LOGIN_THROTTLE_FAIL_OPEN', true),

    'layers' => [
        // L1 — brute-force normal: per (identifier + IP)
        'identifier_ip' => [
            'max_attempts' => (int) env('LOGIN_L1_MAX', 5),
            'decay_seconds' => (int) env('LOGIN_L1_DECAY', 15 * 60),
        ],
        // L2 — credential stuffing / spray: per IP (semua identifier)
        'ip' => [
            'max_attempts' => (int) env('LOGIN_L2_MAX', 20),
            'decay_seconds' => (int) env('LOGIN_L2_DECAY', 10 * 60),
        ],
        // L3 — serangan terdistribusi ke 1 akun: per identifier (semua IP)
        'identifier' => [
            'max_attempts' => (int) env('LOGIN_L3_MAX', 10),
            'decay_seconds' => (int) env('LOGIN_L3_DECAY', 60 * 60),
        ],
        // 2FA — percobaan kode TOTP salah: per identifier
        'two_factor' => [
            'max_attempts' => (int) env('LOGIN_2FA_MAX', 5),
            'decay_seconds' => (int) env('LOGIN_2FA_DECAY', 10 * 60),
        ],
    ],
];
