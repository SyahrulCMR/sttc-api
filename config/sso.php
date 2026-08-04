<?php

return [
    'apps' => [
        'siakad' => [
            'redirect_url' => env('SIAKAD_URL') . '/sso/callback',
            'logout_webhook' => env('SIAKAD_URL') . '/api/sso/force-logout',
            'secret' => env('SIAKAD_SECRET'),
        ],
        'lms' => [
            'redirect_url' => env('LMS_URL') . '/sso/callback',
            'logout_webhook' => env('LMS_URL') . '/api/sso/force-logout',
            'secret' => env('LMS_SECRET'),
        ],
        'blog' => [
            'redirect_url' => env('BLOG_URL') . '/sso/callback',
            'logout_webhook' => env('BLOG_URL') . '/api/sso/force-logout',
            'secret' => env('BLOG_SECRET'),
        ],
    ],
    'token_ttl' => 30,
];
