<?php

return [
    'evidence_disk' => env('CREATOR_EVIDENCE_DISK', 'local'),
    'evidence_retention_days' => (int) env('CREATOR_EVIDENCE_RETENTION_DAYS', 90),
    'verification_challenge_hours' => (int) env('CREATOR_VERIFICATION_CHALLENGE_HOURS', 72),

    'upload_session_ttl_minutes' => (int) env('CREATOR_UPLOAD_SESSION_TTL_MINUTES', 30),
    'payout_change_hold_hours' => (int) env('CREATOR_PAYOUT_CHANGE_HOLD_HOURS', 48),
    'max_upload_bytes' => (int) env('CREATOR_MAX_UPLOAD_BYTES', 21_474_836_480),
    'allowed_video_extensions' => [
        'mp4', 'm4v', 'mov', 'mkv', 'webm', 'avi', 'mpeg', 'mpg', 'ts', 'mts', 'm2ts',
    ],
    'allowed_video_mimes' => [
        'video/mp4',
        'video/quicktime',
        'video/x-matroska',
        'video/webm',
        'video/x-msvideo',
        'video/mpeg',
        'video/mp2t',
        'application/octet-stream',
    ],
    'processing_profiles' => [
        'balanced' => [
            'label' => 'Balanced (recommended)',
            'compress_enabled' => true,
            'faststart_enabled' => true,
            'retention_policy' => 'optimized_only',
            'max_resolution' => 1080,
            'hls_480p' => true,
            'hls_720p' => true,
            'hls_1080p' => false,
        ],
        'data_saver' => [
            'label' => 'Data saver',
            'compress_enabled' => true,
            'faststart_enabled' => true,
            'retention_policy' => 'optimized_only',
            'max_resolution' => 720,
            'hls_480p' => true,
            'hls_720p' => true,
            'hls_1080p' => false,
        ],
        'quality' => [
            'label' => 'High quality',
            'compress_enabled' => true,
            'faststart_enabled' => true,
            'retention_policy' => 'optimized_only',
            'max_resolution' => 1080,
            'hls_480p' => true,
            'hls_720p' => true,
            'hls_1080p' => true,
        ],
    ],

    'rent' => [
        'min_minor' => (int) env('CREATOR_RENT_MIN_MINOR', 1_000),
        'max_minor' => (int) env('CREATOR_RENT_MAX_MINOR', 100_000),
        'increment_minor' => (int) env('CREATOR_RENT_INCREMENT_MINOR', 500),
        'default_duration_hours' => (int) env('CREATOR_RENT_DURATION_HOURS', 48),
    ],
    'purchase' => [
        'min_minor' => (int) env('CREATOR_PURCHASE_MIN_MINOR', 2_000),
        'max_minor' => (int) env('CREATOR_PURCHASE_MAX_MINOR', 500_000),
        'increment_minor' => (int) env('CREATOR_PURCHASE_INCREMENT_MINOR', 500),
    ],

    'content_ratings' => [
        'G', 'PG', 'PG-13', '12', '15', '16', '18', 'R', 'TV-G', 'TV-PG', 'TV-14', 'TV-MA',
    ],
    'languages' => [
        'English', 'Luganda', 'Swahili', 'Runyankole', 'Rukiga', 'Lusoga',
        'Acholi', 'Ateso', 'French', 'Arabic', 'Hindi', 'Spanish', 'Portuguese',
    ],
    'countries' => [
        'Uganda', 'Kenya', 'Tanzania', 'Rwanda', 'Burundi', 'South Sudan',
        'Nigeria', 'Ghana', 'South Africa', 'United States', 'United Kingdom',
        'India', 'France', 'Spain', 'Other',
    ],
    'subtitle_extensions' => ['vtt', 'srt', 'ass', 'ssa', 'sub', 'ttml'],
    'support_categories' => [
        'upload_problem',
        'processing_failure',
        'verification',
        'earnings',
        'withdrawal',
        'content_review',
        'other',
    ],

    /*
    |--------------------------------------------------------------------------
    | Creator capability catalogue
    |--------------------------------------------------------------------------
    |
    | These stable keys are stored on each creator permission record. The
    | existing broad permission flags remain in place as security gates while
    | these capabilities provide the finer controls used by the portal.
    |
    */
    'permission_catalog' => [
        'Content' => [
            'create_movies' => 'Create movies',
            'edit_own_movies' => 'Edit own movies',
            'submit_movies' => 'Submit movies for review',
            'create_tv_shows' => 'Create TV shows',
            'manage_seasons' => 'Manage seasons',
            'manage_episodes' => 'Manage episodes',
        ],
        'Publishing' => [
            'publish_without_review' => 'Publish without editorial review',
        ],
        'Media processing' => [
            'upload_media' => 'Upload media',
            'submit_telegram_links' => 'Submit Telegram links',
            'submit_remote_urls' => 'Submit remote URLs',
            'enable_downloads' => 'Enable downloads',
        ],
        'Monetization' => [
            'set_rental_price' => 'Set rental prices',
            'set_purchase_price' => 'Set purchase prices',
            'select_subscription_plans' => 'Select subscription plans',
        ],
        'Analytics' => [
            'view_analytics' => 'View creator analytics',
        ],
        'Finance' => [
            'view_earnings' => 'View earnings',
            'request_withdrawal' => 'Request withdrawals',
        ],
        'Profile' => [
            'manage_public_profile' => 'Manage public creator profile',
        ],
    ],
    'permission_presets' => [
        'applicant' => [
            'create_movies',
            'edit_own_movies',
            'submit_movies',
            'create_tv_shows',
            'manage_seasons',
            'manage_episodes',
            'upload_media',
            'submit_telegram_links',
            'submit_remote_urls',
        ],
        'standard' => [
            'create_movies',
            'edit_own_movies',
            'submit_movies',
            'create_tv_shows',
            'manage_seasons',
            'manage_episodes',
            'upload_media',
            'submit_telegram_links',
            'submit_remote_urls',
            'enable_downloads',
            'view_analytics',
            'view_earnings',
            'manage_public_profile',
        ],
        'monetized' => [
            'create_movies',
            'edit_own_movies',
            'submit_movies',
            'create_tv_shows',
            'manage_seasons',
            'manage_episodes',
            'upload_media',
            'submit_telegram_links',
            'submit_remote_urls',
            'enable_downloads',
            'set_rental_price',
            'set_purchase_price',
            'select_subscription_plans',
            'view_analytics',
            'view_earnings',
            'request_withdrawal',
            'manage_public_profile',
        ],
        'publisher' => [
            'create_movies',
            'edit_own_movies',
            'submit_movies',
            'publish_without_review',
            'create_tv_shows',
            'manage_seasons',
            'manage_episodes',
            'upload_media',
            'submit_telegram_links',
            'submit_remote_urls',
            'enable_downloads',
            'set_rental_price',
            'set_purchase_price',
            'select_subscription_plans',
            'view_analytics',
            'view_earnings',
            'request_withdrawal',
            'manage_public_profile',
        ],
    ],
];
