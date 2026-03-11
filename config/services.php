<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/api/v1/auth/google/callback'),
    ],

    'flutterwave' => [
        'public_key' => env('FLW_PUBLIC_KEY'),
        'secret_key' => env('FLW_SECRET_KEY'),
        'encryption_key' => env('FLW_ENCRYPTION_KEY'),
        'env' => env('FLW_ENV', 'live'),
        'currency' => env('FLW_CURRENCY', 'UGX'),
    ],

    'pawapay' => [
        'env' => env('PAWAPAY_ENV', 'sandbox'),
        'base_url' => env('PAWAPAY_BASE_URL', 'https://api.sandbox.pawapay.io'),
        'api_token' => env('PAWAPAY_API_TOKEN'),
        'verify_callback_signature' => (bool) env('PAWAPAY_VERIFY_CALLBACK_SIGNATURE', false),
    ],

    'cdn' => [
        'base_url' => env('CDN_API_BASE_URL'),
        'api_token' => env('CDN_API_TOKEN'),
        'default_import_mode' => env('CDN_IMPORT_MODE_DEFAULT', 'now'),
        'connect_timeout' => (int) env('CDN_CONNECT_TIMEOUT', 30),
        'timeout' => (int) env('CDN_TIMEOUT', 300),
        'retry_times' => (int) env('CDN_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('CDN_RETRY_SLEEP_MS', 800),
        'force_ip_resolve' => env('CDN_FORCE_IP_RESOLVE', 'v4'),
        'ingest_secret' => env('CDN_INGEST_SECRET'),
        'ingest_endpoint' => env('CDN_INGEST_ENDPOINT', '/api/ingest/asset-source-upload'),
        'fetch_proxy_token' => env('PORTAL_FETCH_PROXY_TOKEN'),
        'python_worker_enabled' => (bool) env('CDN_PYTHON_WORKER_ENABLED', false),
        'python_worker_hosts' => env('CDN_PYTHON_WORKER_HOSTS', 'mobifliks.info,mobifliks.com'),
        'use_playback_manifest' => (bool) env('CDN_USE_PLAYBACK_MANIFEST', true),
    ],

    'telegram' => [
        'ingest_notify_token' => env('TELEGRAM_INGEST_NOTIFY_TOKEN'),
    ],

    'worker_api_token' => (string) env('PORTAL_WORKER_API_TOKEN', ''),

];
