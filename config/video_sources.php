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

];
