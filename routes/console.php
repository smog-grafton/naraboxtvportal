<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule subscription expiration check to run every hour
Schedule::command('subscriptions:expire')->hourly();

// Reconcile pending ioTec Pay transactions every 10 minutes
Schedule::command('iotec:reconcile-pending')->everyTenMinutes();

// Reconcile pending PawaPay deposits every 5 minutes
Schedule::command('pawapay:reconcile-pending')->everyFiveMinutes();

// Mark creator earnings available (after hold period) daily
Schedule::command('creator:mark-earnings-available')->daily();

Artisan::command('cdn:sync-playback-readiness {--limit=300 : Maximum CDN-backed sourceables to inspect} {--queue-missing : Ask CDN to queue optimization when HLS is missing} {--force : Ignore readiness check TTL}', function () {
    $limit = max(1, (int) $this->option('limit'));
    $queueMissing = (bool) $this->option('queue-missing');
    $force = (bool) $this->option('force');
    $service = app(\App\Services\CdnPlaybackReadinessService::class);

    $rows = \App\Models\VideoSource::query()
        ->whereNotNull('metadata->cdn_source_id')
        ->latest('id')
        ->limit($limit * 4)
        ->get(['id', 'sourceable_type', 'sourceable_id'])
        ->unique(fn (\App\Models\VideoSource $source): string => $source->sourceable_type . ':' . $source->sourceable_id)
        ->take($limit);

    $checked = 0;
    $queued = 0;
    $sourceables = 0;

    foreach ($rows as $row) {
        $modelClass = (string) $row->sourceable_type;
        if (! class_exists($modelClass)) {
            continue;
        }

        $sourceable = $modelClass::find($row->sourceable_id);
        if (! $sourceable) {
            continue;
        }

        $result = $service->syncForSourceable($sourceable, $queueMissing, $force);
        $checked += (int) ($result['checked'] ?? 0);
        $queued += (int) ($result['queued'] ?? 0);
        $sourceables++;
    }

    $this->info("Checked {$checked} CDN URL(s) across {$sourceables} sourceable record(s); queued {$queued} CDN optimization request(s).");
})->purpose('Verify CDN HLS/MP4 readiness and promote ready HLS sources to primary playback');

Schedule::command('cdn:sync-playback-readiness --queue-missing')
    ->name('cdn:sync-playback-readiness')
    ->withoutOverlapping()
    ->everyTenMinutes();

Schedule::command('bunny:sync-stream-sources')
    ->name('bunny:sync-stream-sources')
    ->withoutOverlapping()
    ->everyFiveMinutes();

if ((bool) config('services.nbx_engine.scheduled_sync_enabled', true)) {
    $minutes = max(1, (int) config('services.nbx_engine.scheduled_sync_every_minutes', 5));
    Schedule::command('nbx:sync-video-sources --limit=100')
        ->name('nbx:sync-video-sources')
        ->withoutOverlapping()
        ->everyFiveMinutes()
        ->when(fn (): bool => $minutes <= 5 || now()->minute % $minutes === 0);
}

if ((bool) config('services.nbx_engine.scheduled_backfill_enabled', false)) {
    $limit = max(1, (int) config('services.nbx_engine.scheduled_backfill_limit', 10));
    Schedule::command('nbx:backfill-contabo-sources --limit=' . $limit)
        ->name('nbx:backfill-contabo-sources')
        ->withoutOverlapping()
        ->hourly();
}

// Keep contabo imports processing via cron-driven scheduler.
// This pattern is safe with shared hosting or when Supervisor is unavailable.
Schedule::command('queue:work database --queue=contabo-imports --timeout=21600 --tries=1 --sleep=3 --stop-when-empty')
    ->name('queue:work:contabo-imports')
    ->withoutOverlapping(30)
    ->runInBackground()
    ->everyMinute();

// Process app jobs on the default queue when no long-running supervisor is available.
Schedule::command('queue:work database --queue=default --timeout=3600 --tries=3 --sleep=3 --stop-when-empty')
    ->name('queue:work:default')
    ->withoutOverlapping(30)
    ->runInBackground()
    ->everyMinute();

// Process email campaigns, transactional mail, and admin alerts separately so communication jobs
// are not blocked behind heavier queues like imports or CDN work.
Schedule::command('queue:work database --queue=communications --timeout=3600 --tries=3 --sleep=3 --stop-when-empty')
    ->name('queue:work:communications')
    ->withoutOverlapping(30)
    ->runInBackground()
    ->everyMinute();

// Gracefully restart long workers so fresh code/config is picked up.
Schedule::command('queue:restart')
    ->name('queue:restart:scheduled')
    ->everyFifteenMinutes();
