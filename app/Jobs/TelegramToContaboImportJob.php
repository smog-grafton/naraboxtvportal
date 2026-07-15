<?php

namespace App\Jobs;

use App\Models\VideoSource;
use App\Services\ContaboObjectStorageService;
use App\Services\TelebotClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class TelegramToContaboImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2000;

    public int $timeout = 21600;

    public bool $failOnTimeout = true;

    public function __construct(public int $videoSourceId)
    {
    }

    public function handle(TelebotClientService $telebot, ContaboObjectStorageService $contabo): void
    {
        $source = VideoSource::find($this->videoSourceId);

        if (! $source) {
            return;
        }

        $metadata = (array) ($source->metadata ?? []);
        $telegramUrl = trim((string) ($metadata['telegram_url'] ?? $metadata['source_url'] ?? $source->url ?? ''));

        if ($telegramUrl === '') {
            $this->failSource($source, 'Missing Telegram URL.');
            return;
        }

        if (! $telebot->isConfigured()) {
            $this->failSource($source, $telebot->configurationError());
            return;
        }

        if ($this->hasTimedOut($metadata)) {
            $this->failSource($source, 'Tele-OB import timed out before telebot produced a downloadable temp URL.');
            return;
        }

        $jobId = trim((string) ($metadata['telebot_job_id'] ?? ''));

        if ($jobId === '') {
            if (! $this->workerHasCapacity($telebot, $source)) {
                $this->release($this->capacityRetrySeconds());
                return;
            }

            $createResult = $telebot->createDownloadJob($telegramUrl, [
                'video_source_id' => $source->id,
                'sourceable_type' => $source->sourceable_type,
                'sourceable_id' => $source->sourceable_id,
                'storage_target' => 'contabo_object_storage',
                'provider' => 'tele_ob',
                'quality' => $source->quality ?? 'auto',
                'format' => $source->format ?? 'auto',
            ]);

            if (! ($createResult['ok'] ?? false)) {
                if (($createResult['status_code'] ?? null) === 429) {
                    $this->markWaiting($source, (string) ($createResult['error'] ?? 'Telebot is currently full.'));
                    $this->release($this->capacityRetrySeconds());
                    return;
                }

                $this->failSource($source, (string) ($createResult['error'] ?? 'Telebot job creation failed.'));
                return;
            }

            $jobId = (string) ($createResult['job_id'] ?? '');
            $this->mergeMetadata($source, [
                'fetch_status' => 'processing',
                'telegram_status' => 'fetching',
                'telebot_job_id' => $jobId,
                'telebot_started_at' => now()->toDateTimeString(),
                'last_message' => 'Telebot accepted the Telegram download job.',
            ]);

            $this->release($this->pollIntervalSeconds());
            return;
        }

        $statusResult = $telebot->jobStatus($jobId);

        if (! ($statusResult['ok'] ?? false)) {
            $this->failSource($source, (string) ($statusResult['error'] ?? 'Could not read telebot job status.'));
            return;
        }

        $statusData = is_array($statusResult['data'] ?? null) ? $statusResult['data'] : [];
        $status = (string) ($statusData['status'] ?? '');

        if (in_array($status, ['queued', 'downloading', 'waiting_to_prepare', 'preparing'], true)) {
            $this->mergeMetadata($source, [
                'fetch_status' => $status === 'queued' ? 'queued' : 'processing',
                'telegram_status' => 'fetching',
                'telebot_status' => $status,
                'telebot_progress' => $statusData['progress_pct'] ?? null,
                'last_message' => (string) ($statusData['message'] ?? 'Telebot is downloading the Telegram video.'),
                'last_polled_at' => now()->toDateTimeString(),
            ]);

            $this->release($this->pollIntervalSeconds());
            return;
        }

        if (! in_array($status, ['downloaded', 'done'], true)) {
            $this->failSource(
                $source,
                (string) ($statusData['error'] ?? $statusData['message'] ?? ('Telebot job finished with status ' . ($status ?: 'unknown') . '.')),
                $statusData
            );
            return;
        }

        $tempUrl = $telebot->absoluteUrl(isset($statusData['temp_url']) ? (string) $statusData['temp_url'] : null);

        if (! $tempUrl) {
            $this->failSource($source, 'Telebot finished but did not return a temp URL.', $statusData);
            return;
        }

        $this->mergeMetadata($source, [
            'fetch_status' => 'processing',
            'telegram_status' => 'fetching',
            'telebot_status' => $status,
            'telebot_temp_url' => $tempUrl,
            'last_message' => 'Telegram temp file is ready. Streaming into Contabo Object Storage.',
            'last_polled_at' => now()->toDateTimeString(),
        ]);

        $result = $contabo->fetchUrlToBucket(
            $tempUrl,
            $source->sourceable_type,
            (int) $source->sourceable_id,
            $source->sourceable_type === 'App\Models\Episode' ? 'episode' : 'movie',
            (string) ($source->quality ?? 'auto'),
            (string) ($source->format ?? 'auto')
        );

        if (! ($result['ok'] ?? false)) {
            $this->failSource($source->refresh(), (string) ($result['error'] ?? 'Contabo Object Storage upload failed.'), $statusData);
            return;
        }

        $publicUrl = (string) ($result['public_url'] ?? '');
        $objectKey = isset($result['key']) ? (string) $result['key'] : null;
        $resolvedFormat = $this->resolveFormat($source, $publicUrl);

        $source->refresh()->update([
            'type' => 'tele_ob',
            'url' => $publicUrl,
            'file_path' => $publicUrl,
            'format' => $resolvedFormat,
            'file_size' => isset($result['file_size']) ? (int) $result['file_size'] : $source->file_size,
            'is_active' => true,
            'metadata' => array_merge((array) ($source->metadata ?? []), [
                'provider' => 'tele_ob',
                'storage_target' => 'contabo_object_storage',
                'fetch_status' => 'completed',
                'fetch_mode' => 'tele_ob_queue',
                'telegram_status' => 'attached',
                'telebot_status' => $status,
                'telegram_url' => $telegramUrl,
                'source_url' => $telegramUrl,
                'telebot_temp_url' => $tempUrl,
                'object_key' => $objectKey,
                'bucket' => $contabo->bucket(),
                'endpoint' => $contabo->endpoint(),
                'public_url' => $publicUrl,
                'download_url' => $publicUrl,
                'mp4_url' => $publicUrl,
                'playback_type' => strtolower($resolvedFormat) === 'm3u8' ? 'hls' : 'mp4',
                'last_message' => 'Telegram video stored on Contabo Object Storage.',
                'completed_at' => now()->toDateTimeString(),
                'last_synced_at' => now()->toDateTimeString(),
            ]),
        ]);

        if ((bool) config('services.telebot.destroy_after_import', true)) {
            $destroyResult = $telebot->destroyJob($jobId);

            if (! ($destroyResult['ok'] ?? false)) {
                $this->mergeMetadata($source->refresh(), [
                    'telebot_destroy_error' => $destroyResult['error'] ?? 'Telebot temp cleanup failed.',
                ]);
            } else {
                $this->mergeMetadata($source->refresh(), [
                    'telebot_destroyed_at' => now()->toDateTimeString(),
                ]);
            }
        }
    }

    private function workerHasCapacity(TelebotClientService $telebot, VideoSource $source): bool
    {
        $capacity = $telebot->capacity();

        if (! ($capacity['ok'] ?? false)) {
            $this->markWaiting($source, (string) ($capacity['error'] ?? 'Could not read telebot worker capacity.'));
            return false;
        }

        $data = is_array($capacity['data'] ?? null) ? $capacity['data'] : [];
        $activeJobs = (int) ($data['active_jobs'] ?? 0);
        $workerLimit = max(1, (int) ($data['max_active_jobs'] ?? 2));
        $portalLimit = max(1, (int) config('services.telebot.max_active_jobs', 2));
        $limit = min($workerLimit, $portalLimit);

        if (! ($data['available'] ?? false) || $activeJobs >= $limit) {
            $this->markWaiting($source, 'Waiting for telebot capacity. Active jobs: ' . $activeJobs . '/' . $limit . '.');
            return false;
        }

        return true;
    }

    private function markWaiting(VideoSource $source, string $message): void
    {
        $this->mergeMetadata($source, [
            'fetch_status' => 'queued',
            'telegram_status' => 'telegram_submitted',
            'last_message' => $message,
            'last_polled_at' => now()->toDateTimeString(),
        ]);
    }

    private function failSource(VideoSource $source, string $message, array $telebotData = []): void
    {
        $this->mergeMetadata($source, [
            'fetch_status' => 'failed',
            'telegram_status' => 'failed',
            'telebot_status' => $telebotData['status'] ?? null,
            'telebot_error' => $telebotData['error'] ?? null,
            'last_message' => $message,
            'completed_at' => now()->toDateTimeString(),
        ]);
    }

    private function mergeMetadata(VideoSource $source, array $updates): void
    {
        $source->update([
            'metadata' => array_merge((array) ($source->metadata ?? []), $updates),
        ]);
    }

    private function hasTimedOut(array $metadata): bool
    {
        $startedAt = $metadata['telebot_started_at'] ?? null;

        if (! is_string($startedAt) || trim($startedAt) === '') {
            return false;
        }

        try {
            return Carbon::parse($startedAt)->diffInSeconds(now()) > (int) config('services.telebot.job_timeout', 21600);
        } catch (\Throwable) {
            return false;
        }
    }

    private function pollIntervalSeconds(): int
    {
        return max(5, (int) config('services.telebot.job_poll_interval', 15));
    }

    private function capacityRetrySeconds(): int
    {
        return max(10, (int) config('services.telebot.capacity_retry_seconds', 60));
    }

    private function resolveFormat(VideoSource $source, string $publicUrl): string
    {
        $format = strtolower((string) ($source->format ?? ''));

        if ($format !== '' && $format !== 'auto') {
            return $format;
        }

        $path = parse_url($publicUrl, PHP_URL_PATH);
        $extension = is_string($path) ? pathinfo($path, PATHINFO_EXTENSION) : '';

        return $extension !== '' ? strtolower($extension) : 'mp4';
    }
}
