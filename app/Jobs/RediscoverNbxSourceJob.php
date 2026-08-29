<?php

namespace App\Jobs;

use App\Models\VideoSource;
use App\Services\NbxCatalogRecoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tier 1 resync: cheap re-discovery against NBX's (possibly rebuilt-from-storage)
 * catalog. Safe to run broadly — it never re-fetches or re-transcodes.
 */
class RediscoverNbxSourceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

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
            $result = $service->rediscover($source);
            ResyncRunTracker::increment($this->runCacheKey, $result['matched'] ? 'matched' : 'unmatched');
        } catch (\Throwable $exception) {
            Log::warning('nbx-resync tier1 failed', ['video_source_id' => $this->videoSourceId, 'error' => $exception->getMessage()]);
            ResyncRunTracker::increment($this->runCacheKey, 'failed');
        }
    }
}
