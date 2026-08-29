<?php

return [
    'enabled' => env('SECURITY_ENABLED', true),
    'cache_store' => env('SECURITY_CACHE_STORE', env('CACHE_STORE', 'database')),
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SECURITY_TRUSTED_PROXIES', ''))
    ))),
    'device_header' => 'X-NBX-Device-ID',
    'device_id_max_length' => 128,
    'registration' => [
        'enabled' => env('REGISTRATION_SECURITY_ENABLED', true),
        'ip' => [
            'max_attempts' => (int) env('REGISTRATION_IP_MAX_ATTEMPTS', 3),
            'window_minutes' => (int) env('REGISTRATION_IP_WINDOW_MINUTES', 60),
        ],
        'email' => [
            'max_attempts' => (int) env('REGISTRATION_EMAIL_MAX_ATTEMPTS', 3),
            'window_minutes' => (int) env('REGISTRATION_EMAIL_WINDOW_MINUTES', 1440),
        ],
        'device' => [
            'max_attempts' => (int) env('REGISTRATION_DEVICE_MAX_ATTEMPTS', 3),
            'window_minutes' => (int) env('REGISTRATION_DEVICE_WINDOW_MINUTES', 60),
        ],
    ],
];
