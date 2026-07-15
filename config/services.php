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
        'remote_playback_manifest_lookup' => (bool) env('CDN_REMOTE_PLAYBACK_MANIFEST_LOOKUP', false),
        'hls_readiness_check_enabled' => (bool) env('CDN_HLS_READINESS_CHECK_ENABLED', true),
        'hls_readiness_check_on_playback' => (bool) env('CDN_HLS_READINESS_CHECK_ON_PLAYBACK', false),
        'hls_readiness_ttl_minutes' => (int) env('CDN_HLS_READINESS_TTL_MINUTES', 30),
        'hls_readiness_connect_timeout' => (int) env('CDN_HLS_READINESS_CONNECT_TIMEOUT', 3),
        'hls_readiness_timeout' => (int) env('CDN_HLS_READINESS_TIMEOUT', 8),
        'hls_auto_deactivate_unready' => (bool) env('CDN_HLS_AUTO_DEACTIVATE_UNREADY', true),
        'hls_auto_queue_missing' => (bool) env('CDN_HLS_AUTO_QUEUE_MISSING', false),
    ],

    'nbx_engine' => [
        'enabled' => (bool) env('NBX_ENGINE_ENABLED', false),
        'base_url' => env('NBX_ENGINE_BASE_URL'),
        'api_key' => env('NBX_ENGINE_API_KEY'),
        'webhook_secret' => env('NBX_ENGINE_WEBHOOK_SECRET'),
        'callback_url' => env('NBX_ENGINE_CALLBACK_URL'),
        'webhook_tolerance_seconds' => (int) env('NBX_WEBHOOK_TOLERANCE_SECONDS', 300),
        'scheduled_sync_enabled' => (bool) env('NBX_SCHEDULED_SYNC_ENABLED', true),
        'scheduled_sync_every_minutes' => (int) env('NBX_SCHEDULED_SYNC_EVERY_MINUTES', 5),
        'scheduled_backfill_enabled' => (bool) env('NBX_SCHEDULED_BACKFILL_ENABLED', false),
        'scheduled_backfill_limit' => (int) env('NBX_SCHEDULED_BACKFILL_LIMIT', 10),
        'scheduled_cleanup_enabled' => (bool) env('NBX_SCHEDULED_CLEANUP_ENABLED', true),
        'timeout' => (int) env('NBX_ENGINE_TIMEOUT', 300),
        'connect_timeout' => (int) env('NBX_ENGINE_CONNECT_TIMEOUT', 15),
        'retry_times' => (int) env('NBX_ENGINE_RETRY_TIMES', 1),
        'retry_sleep_ms' => (int) env('NBX_ENGINE_RETRY_SLEEP_MS', 800),
        'source_discovery' => (bool) env('NBX_ENGINE_SOURCE_DISCOVERY', true),
        'legacy_cdn_base_url' => env('NBX_LEGACY_CDN_BASE_URL', env('CDN_API_BASE_URL')),
    ],

    'bunny_stream' => [
        'enabled' => (bool) env('BUNNY_STREAM_ENABLED', false),
        'api_base_url' => env('BUNNY_STREAM_API_BASE_URL', 'https://video.bunnycdn.com'),
        'api_key' => env('BUNNY_STREAM_API_KEY'),
        'library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
        'pull_zone_hostname' => env('BUNNY_STREAM_PULL_ZONE_HOSTNAME'),
        'collection_id' => env('BUNNY_STREAM_COLLECTION_ID'),
        'timeout' => (int) env('BUNNY_STREAM_TIMEOUT', 300),
        'connect_timeout' => (int) env('BUNNY_STREAM_CONNECT_TIMEOUT', 15),
        'retry_times' => (int) env('BUNNY_STREAM_RETRY_TIMES', 1),
        'retry_sleep_ms' => (int) env('BUNNY_STREAM_RETRY_SLEEP_MS', 800),
        'enabled_resolutions' => env('BUNNY_STREAM_ENABLED_RESOLUTIONS', ''),
        'refresh_metadata_on_playback' => (bool) env('BUNNY_STREAM_REFRESH_METADATA_ON_PLAYBACK', false),
    ],

    'contabo_object_storage' => [
        'enabled' => (bool) env('CONTABO_OBJECT_STORAGE_ENABLED', false),
        'disk' => env('CONTABO_OBJECT_STORAGE_DISK', 'contabo'),
        'endpoint' => env('CONTABO_OBJECT_STORAGE_ENDPOINT', 'https://usc1.contabostorage.com'),
        'bucket' => env('CONTABO_OBJECT_STORAGE_BUCKET', 'nbx'),
        'public_url' => env('CONTABO_OBJECT_STORAGE_PUBLIC_URL', 'https://usc1.contabostorage.com/d052ede4e40a478d92ab1a7ad3f1e435:nbx'),
        'path_prefix' => env('CONTABO_OBJECT_STORAGE_PATH_PREFIX', 'videos'),
        'visibility' => env('CONTABO_OBJECT_STORAGE_VISIBILITY', 'public'),
        'connect_timeout' => (int) env('CONTABO_OBJECT_STORAGE_CONNECT_TIMEOUT', 30),
        'timeout' => (int) env('CONTABO_OBJECT_STORAGE_TIMEOUT', 21600),
    ],

    'contabo_api' => [
        'base_url' => env('CONTABO_API_BASE_URL', 'https://api.contabo.com'),
        'auth_url' => env('CONTABO_AUTH_URL', 'https://auth.contabo.com/auth/realms/contabo/protocol/openid-connect/token'),
        'client_id' => env('CONTABO_API_CLIENT_ID'),
        'client_secret' => env('CONTABO_API_CLIENT_SECRET'),
        'username' => env('CONTABO_API_USERNAME'),
        'password' => env('CONTABO_API_PASSWORD'),
        'user_id' => env('CONTABO_API_USER_ID'),
        'object_storage_id' => env('CONTABO_API_OBJECT_STORAGE_ID'),
        'timeout' => (int) env('CONTABO_API_TIMEOUT', 30),
        'connect_timeout' => (int) env('CONTABO_API_CONNECT_TIMEOUT', 10),
    ],

    'telegram' => [
        'ingest_notify_token' => env('TELEGRAM_INGEST_NOTIFY_TOKEN'),
    ],

    'telebot' => [
        'base_url' => env('TELEBOT_API_BASE_URL', 'https://teletyde.nara24fm.com'),
        'api_token' => env('TELEBOT_WORKER_API_TOKEN'),
        'connect_timeout' => (int) env('TELEBOT_CONNECT_TIMEOUT', 10),
        'timeout' => (int) env('TELEBOT_TIMEOUT', 30),
        'job_timeout' => (int) env('TELEBOT_JOB_TIMEOUT', 21600),
        'job_poll_interval' => (int) env('TELEBOT_JOB_POLL_INTERVAL', 15),
        'capacity_retry_seconds' => (int) env('TELEBOT_CAPACITY_RETRY_SECONDS', 60),
        'max_active_jobs' => (int) env('TELEBOT_MAX_ACTIVE_JOBS', 2),
        'max_portal_objects' => (int) env('TELEBOT_MAX_PORTAL_OBJECTS', 3),
        'destroy_after_import' => (bool) env('TELEBOT_DESTROY_AFTER_IMPORT', true),
    ],

    'creator' => [
        'direct_upload_max_mb' => (int) env('CREATOR_DIRECT_UPLOAD_MAX_MB', 600),
    ],

    'worker_api_token' => (string) env('PORTAL_WORKER_API_TOKEN', ''),

];
