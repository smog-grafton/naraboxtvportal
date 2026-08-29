<?php

namespace App\Jobs;

use App\Models\VideoSource;
use App\Services\NbxCatalogRecoveryService;
use App\Services\ResyncRunTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Tier 2 resync: full re-fetch + re-transcode from the original source URL.
 * Only meant to run for sources tier 1 (RediscoverNbxSourceJob) could not
 * match — this costs real bandwidth/compute on NBX, so it is never queued
 * automatically; a caller must explicitly decide a source needs it.
 */
class RecreateNbxSourceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $videoSourceId, public string $runCacheKey)
    {
        $this->onQueue('nbx-resync');
    }

    public function handle(NbxCatalogRecoveryService $service): void
    {
        $source = VideoSource::find($this->videoSourceId);
        if (! $source) {
            return;
        }

        try {
            $service->resubmitForRecreation($source);
            ResyncRunTracker::increment($this->runCacheKey, 'recreated');
        } catch (\Throwable $exception) {
            Log::warning('nbx-resync tier2 failed', ['video_source_id' => $this->videoSourceId, 'error' => $exception->getMessage()]);
            ResyncRunTracker::increment($this->runCacheKey, 'failed');
        }
    }
}
