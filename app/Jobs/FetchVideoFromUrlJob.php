<?php

namespace App\Jobs;

use App\Http\Controllers\Api\VideoFetchController;
use App\Models\VideoSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchVideoFromUrlJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 21600;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $videoSourceId,
        public string $url,
        public string $sourceableType,
        public int $sourceableId,
        public string $quality = 'auto',
        public string $format = 'auto',
        public string $storageTarget = 'cdn',
    ) {
    }

    public function handle(): void
    {
        $videoSource = VideoSource::find($this->videoSourceId);

        if (!$videoSource) {
            return;
        }

        $metadata = (array) ($videoSource->metadata ?? []);
        $videoSource->update([
            'metadata' => array_merge($metadata, [
                'fetch_status' => 'processing',
                'fetch_mode' => 'queue',
                'started_at' => now()->toDateTimeString(),
                'last_message' => 'Background fetch started.',
            ]),
        ]);

        try {
            $controller = app(VideoFetchController::class);

            $request = new Request([
                'url' => $this->url,
                'sourceable_type' => $this->sourceableType,
                'sourceable_id' => $this->sourceableId,
                'quality' => $this->quality,
                'format' => $this->format,
                'import_mode' => 'now',
                'storage_target' => $this->storageTarget,
            ]);

            $response = $controller->fetch($request);
            $responseData = json_decode($response->getContent(), true);

            if ($response->getStatusCode() === 200 && ($responseData['success'] ?? false)) {
                $updatedSourceId = (int) ($responseData['video_source']['id'] ?? $videoSource->id);
                $updatedSource = VideoSource::find($updatedSourceId) ?? $videoSource;

                $updatedMetadata = (array) ($updatedSource->metadata ?? []);
                $updatedSource->update([
                    'metadata' => array_merge($updatedMetadata, [
                        'fetch_status' => 'completed',
                        'fetch_mode' => 'queue',
                        'completed_at' => now()->toDateTimeString(),
                        'last_message' => 'Background fetch completed successfully.',
                    ]),
                ]);

                return;
            }

            $failedMetadata = (array) ($videoSource->metadata ?? []);
            $videoSource->update([
                'metadata' => array_merge($failedMetadata, [
                    'fetch_status' => 'failed',
                    'fetch_mode' => 'queue',
                    'completed_at' => now()->toDateTimeString(),
                    'last_message' => (string) ($responseData['message'] ?? 'Background fetch failed.'),
                ]),
            ]);
        } catch (\Throwable $e) {
            $failedMetadata = (array) ($videoSource->metadata ?? []);
            $videoSource->update([
                'metadata' => array_merge($failedMetadata, [
                    'fetch_status' => 'failed',
                    'fetch_mode' => 'queue',
                    'completed_at' => now()->toDateTimeString(),
                    'last_message' => $e->getMessage(),
                ]),
            ]);
        }
    }
}
