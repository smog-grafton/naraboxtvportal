<?php

return [
    'enabled' => env('PAYMENT_SECURITY_ENABLED', true),
    'user' => ['max_attempts' => (int) env('PAYMENT_USER_MAX_ATTEMPTS', 10), 'window_minutes' => (int) env('PAYMENT_USER_WINDOW_MINUTES', 60)],
    'provider' => ['max_attempts' => (int) env('PAYMENT_PROVIDER_ID_MAX_ATTEMPTS', 10), 'window_minutes' => (int) env('PAYMENT_PROVIDER_ID_WINDOW_MINUTES', 60)],
    'ip' => ['max_attempts' => (int) env('PAYMENT_IP_MAX_ATTEMPTS', 20), 'window_minutes' => (int) env('PAYMENT_IP_WINDOW_MINUTES', 60)],
    'device' => ['max_attempts' => (int) env('PAYMENT_DEVICE_MAX_ATTEMPTS', 10), 'window_minutes' => (int) env('PAYMENT_DEVICE_WINDOW_MINUTES', 60)],
    'payer' => ['max_attempts' => (int) env('PAYMENT_PAYER_MAX_ATTEMPTS', 5), 'window_minutes' => (int) env('PAYMENT_PAYER_WINDOW_MINUTES', 60)],
    'minimum_seconds_between_attempts' => (int) env('PAYMENT_MIN_SECONDS_BETWEEN_ATTEMPTS', 20),
    'max_pending_per_user' => (int) env('PAYMENT_MAX_PENDING_PER_USER', 1),
    'pending_expiry_minutes' => (int) env('PAYMENT_PENDING_EXPIRY_MINUTES', 15),
    'unique_payers' => [
        'window_minutes' => (int) env('PAYMENT_UNIQUE_PAYER_WINDOW_MINUTES', 1440),
        'max_per_user' => (int) env('PAYMENT_MAX_UNIQUE_PAYERS_PER_USER', 3),
        'max_per_provider' => (int) env('PAYMENT_MAX_UNIQUE_PAYERS_PER_PROVIDER', 3),
        'max_per_ip' => (int) env('PAYMENT_MAX_UNIQUE_PAYERS_PER_IP', 5),
        'max_per_device' => (int) env('PAYMENT_MAX_UNIQUE_PAYERS_PER_DEVICE', 3),
    ],
    'enumeration' => [
        'enabled' => env('PAYMENT_ENUMERATION_ENABLED', true),
        'window_minutes' => (int) env('PAYMENT_ENUMERATION_WINDOW_MINUTES', 30),
        'minimum_unique_numbers' => (int) env('PAYMENT_ENUMERATION_MIN_UNIQUE_NUMBERS', 5),
        'prefix_length' => (int) env('PAYMENT_ENUMERATION_PREFIX_LENGTH', 9),
        'maximum_numeric_spread' => (int) env('PAYMENT_ENUMERATION_MAX_NUMERIC_SPREAD', 100),
        'auto_restrict' => env('PAYMENT_ENUMERATION_AUTO_RESTRICT', true),
    ],
    'new_account' => [
        'enabled' => env('NEW_ACCOUNT_PAYMENT_PROTECTION_ENABLED', true),
        'window_minutes' => (int) env('NEW_ACCOUNT_PAYMENT_WINDOW_MINUTES', 30),
        'max_attempts' => (int) env('NEW_ACCOUNT_PAYMENT_MAX_ATTEMPTS', 3),
    ],
    'lock_seconds' => (int) env('PAYMENT_SECURITY_LOCK_SECONDS', 30),
];
