<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default values for video sources (manual and auto-created)
    |--------------------------------------------------------------------------
    | Used by Filament relation manager forms and by automatic creation of
    | sibling sources (MP4 Play, HLS Master, variants). Keeps UX consistent.
    */

    'defaults' => [
        'quality' => env('VIDEO_SOURCES_DEFAULT_QUALITY', '480p'),
        'format' => env('VIDEO_SOURCES_DEFAULT_FORMAT', 'mp4'),
        'file_size' => (int) env('VIDEO_SOURCES_DEFAULT_FILE_SIZE', 380_000_000),
        'duration_seconds' => (int) env('VIDEO_SOURCES_DEFAULT_DURATION', 7200),
        'is_primary' => filter_var(env('VIDEO_SOURCES_DEFAULT_IS_PRIMARY', false), FILTER_VALIDATE_BOOLEAN),
        'is_active' => filter_var(env('VIDEO_SOURCES_DEFAULT_IS_ACTIVE', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'health' => [
        'connect_timeout_seconds' => (int) env('VIDEO_SOURCE_HEALTH_CONNECT_TIMEOUT', 3),
        'timeout_seconds' => (int) env('VIDEO_SOURCE_HEALTH_TIMEOUT', 8),
        'retry_times' => (int) env('VIDEO_SOURCE_HEALTH_RETRY_TIMES', 1),
        'retry_sleep_ms' => (int) env('VIDEO_SOURCE_HEALTH_RETRY_SLEEP_MS', 250),
        'ttl_minutes' => (int) env('VIDEO_SOURCE_HEALTH_TTL_MINUTES', 30),
        'failure_threshold' => (int) env('VIDEO_SOURCE_HEALTH_FAILURE_THRESHOLD', 2),
        'candidate_limit' => (int) env('VIDEO_SOURCE_CANDIDATE_LIMIT', 5),
    ],

];
