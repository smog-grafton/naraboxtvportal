<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Logical storage targets
    |--------------------------------------------------------------------------
    |
    | Each entry is a stable logical key that Portal and NBX both understand.
    | Adding a new bucket/storage service later means adding one entry here
    | (plus matching env vars and, on NBX, a matching entry in its own
    | config/storage_targets.php) — no media table schema changes required.
    |
    | Secrets (access key / secret key) live only in filesystems.php disks,
    | resolved from env. Never store them here or in the database.
    |
    */

    'targets' => [

        // Falls back to the pre-existing single-bucket CONTABO_OBJECT_STORAGE_*
        // vars wherever a new CONTABO_NBX_* var is not set, so upgrading this
        // app does not require touching production env vars immediately.
        'contabo_nbx' => [
            'label' => 'NaraBox Legacy Storage — nbx',
            'provider' => 'contabo',
            // Defaults to the SAME disk key ("contabo") the app already used
            // for its single bucket — this target is meant to be
            // indistinguishable from current production behavior unless an
            // operator explicitly opts into a separate disk/credentials via
            // CONTABO_NBX_DISK.
            'disk' => env('CONTABO_NBX_DISK', 'contabo'),
            'endpoint' => env('CONTABO_NBX_ENDPOINT', env('CONTABO_OBJECT_STORAGE_ENDPOINT', 'https://usc1.contabostorage.com')),
            'region' => env('CONTABO_NBX_REGION', env('CONTABO_OBJECT_STORAGE_REGION', 'US-central')),
            'bucket' => env('CONTABO_NBX_BUCKET', env('CONTABO_OBJECT_STORAGE_BUCKET', 'nbx')),
            'public_url' => env('CONTABO_NBX_PUBLIC_URL', env('CONTABO_OBJECT_STORAGE_PUBLIC_URL', 'https://usc1.contabostorage.com/d052ede4e40a478d92ab1a7ad3f1e435:nbx')),
            'path_prefix' => env('CONTABO_NBX_PATH_PREFIX', env('CONTABO_OBJECT_STORAGE_PATH_PREFIX', 'videos')),
            'enabled' => (bool) env('CONTABO_NBX_ENABLED', env('CONTABO_OBJECT_STORAGE_ENABLED', true)),
            'writable' => (bool) env('CONTABO_NBX_WRITABLE', true),
            'priority' => (int) env('CONTABO_NBX_PRIORITY', 20),
            'capacity_bytes' => (int) env('CONTABO_NBX_CAPACITY_BYTES', 536870912000), // 500GB
            'reserve_percent' => (float) env('CONTABO_NBX_RESERVE_PERCENT', 10),
            // Dashboard-known usage fallback (Contabo does not expose a bucket
            // quota API): seeded from the operator-observed value and refreshed
            // manually or via incremental accounting. See StorageUsageService.
            'known_used_bytes' => (int) env('CONTABO_NBX_KNOWN_USED_BYTES', 471073193164), // ~438.66 GB
            'known_used_at' => env('CONTABO_NBX_KNOWN_USED_AT'),
        ],

        'contabo_nb_nbx' => [
            'label' => 'NaraBox Storage 2 — nb-nbx',
            'provider' => 'contabo',
            'disk' => env('CONTABO_NB_NBX_DISK', 'contabo_nb_nbx'),
            'endpoint' => env('CONTABO_NB_NBX_ENDPOINT', 'https://usc1.contabostorage.com'),
            'region' => env('CONTABO_NB_NBX_REGION', 'US-central'),
            'bucket' => env('CONTABO_NB_NBX_BUCKET', 'nb-nbx'),
            // Contabo's public object URLs require the tenant-ID-prefixed
            // path form ("https://{endpoint}/{tenantId}:{bucket}/...") even
            // for path-style buckets — the bare "/nb-nbx/..." form returns
            // {"message":"Unauthorized"}. Confirmed against the actual
            // nb-nbx service's tenant ID.
            'public_url' => env('CONTABO_NB_NBX_PUBLIC_URL', 'https://usc1.contabostorage.com/5fa286e37e8b403abc5b60ba900a5c3d:nb-nbx'),
            'path_prefix' => env('CONTABO_NB_NBX_PATH_PREFIX', env('CONTABO_OBJECT_STORAGE_PATH_PREFIX', 'videos')),
            'enabled' => (bool) env('CONTABO_NB_NBX_ENABLED', true),
            'writable' => (bool) env('CONTABO_NB_NBX_WRITABLE', true),
            'priority' => (int) env('CONTABO_NB_NBX_PRIORITY', 100),
            'capacity_bytes' => (int) env('CONTABO_NB_NBX_CAPACITY_BYTES', 536870912000), // 500GB
            'reserve_percent' => (float) env('CONTABO_NB_NBX_RESERVE_PERCENT', 10),
            'known_used_bytes' => (int) env('CONTABO_NB_NBX_KNOWN_USED_BYTES', 0),
            'known_used_at' => env('CONTABO_NB_NBX_KNOWN_USED_AT'),
        ],

        'r2_nbx' => [
            'label' => 'Cloudflare R2 — nbx',
            'provider' => 'cloudflare_r2',
            'disk' => env('CLOUDFLARE_R2_DISK', 'r2'),
            'endpoint' => env('CLOUDFLARE_R2_ENDPOINT', 'https://e31bdccee36e2432baee084144f9c6ae.r2.cloudflarestorage.com'),
            'region' => env('CLOUDFLARE_R2_REGION', 'auto'),
            'bucket' => env('CLOUDFLARE_R2_BUCKET', 'nbx'),
            'public_url' => env('CLOUDFLARE_R2_PUBLIC_URL', 'https://nbxgen.naraboxtv.com'),
            'path_prefix' => env('CLOUDFLARE_R2_PATH_PREFIX', 'videos'),
            'enabled' => (bool) env('CLOUDFLARE_R2_ENABLED', false),
            'writable' => (bool) env('CLOUDFLARE_R2_WRITABLE', true),
            'priority' => (int) env('CLOUDFLARE_R2_PRIORITY', 200),
            'capacity_bytes' => (int) env('CLOUDFLARE_R2_CAPACITY_BYTES', 10995116277760), // 10 TiB accounting guardrail.
            'reserve_percent' => (float) env('CLOUDFLARE_R2_RESERVE_PERCENT', 5),
            'known_used_bytes' => (int) env('CLOUDFLARE_R2_KNOWN_USED_BYTES', 0),
            'known_used_at' => env('CLOUDFLARE_R2_KNOWN_USED_AT'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Automatic selection
    |--------------------------------------------------------------------------
    */

    'default_target' => env('MEDIA_STORAGE_DEFAULT_TARGET', 'auto'),
    'auto_selection_enabled' => (bool) env('MEDIA_STORAGE_AUTO_SELECTION_ENABLED', true),
    'legacy_target_key' => 'contabo_nbx',

    // How long locally-calculated/known usage figures are trusted before
    // being treated as stale (surfaced in the Filament status page).
    'usage_stale_after_minutes' => (int) env('MEDIA_STORAGE_USAGE_STALE_AFTER_MINUTES', 30),

    // Conservative multiplier applied to input size when estimating output
    // size for a transcode job (HLS + faststart + temp copies can exceed 1x).
    'transcode_safety_multiplier' => (float) env('MEDIA_STORAGE_TRANSCODE_SAFETY_MULTIPLIER', 2.0),

    // Only users with this Filament permission/role may override a soft-cap
    // warning and write to a target that is above its reserved headroom.
    'soft_cap_override_permission' => env('MEDIA_STORAGE_SOFT_CAP_OVERRIDE_PERMISSION', 'storage.override-soft-cap'),
];
