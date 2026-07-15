<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Push Notification Provider
    |--------------------------------------------------------------------------
    |
    | Supported providers out of the box:
    | - "log": log all notifications (safe for local/dev)
    | - "fcm": Firebase Cloud Messaging (wire credentials via env)
    | - "onesignal": OneSignal (wire credentials via env)
    |
    */

    'default' => env('PUSH_PROVIDER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Provider configurations
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'log' => [
            // No credentials required.
        ],

        'fcm' => [
            'server_key' => env('FIREBASE_SERVER_KEY'),
            'project_id' => env('FIREBASE_PROJECT_ID'),
        ],

        'onesignal' => [
            'app_id' => env('ONESIGNAL_APP_ID'),
            'api_key' => env('ONESIGNAL_API_KEY'),
            'api_base_url' => env('ONESIGNAL_API_BASE_URL', 'https://api.onesignal.com'),
        ],
    ],
];
