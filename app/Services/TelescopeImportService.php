<?php

namespace App\Services;

use App\Models\VideoSource;
use App\Services\Media\TelegramProcessingProfile;
use App\Services\Storage\StorageTargetRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TelescopeImportService
{
    public function __construct(private readonly TelebotClientService $telebot) {}

    public function submit(Model $sourceable, array $data, string $assetType): VideoSource
    {
        $telegramUrl = trim((string) ($data['url'] ?? ''));
        if (! preg_match('~^(?:https?://(?:www\.)?(?:t\.me|telegram\.me|telegram\.dog)/|tg:)~i', $telegramUrl)) {
            throw new \RuntimeException('Please enter a valid Telegram message URL for Telescope.');
        }

        $storageTarget = trim((string) ($data['nbx_storage_target'] ?? 'auto')) ?: 'auto';
        if (! array_key_exists($storageTarget, app(StorageTargetRegistry::class)->selectOptions())) {
            throw ValidationException::withMessages(['nbx_storage_target' => 'Select a configured storage destination.']);
        }
        $processing = TelegramProcessingProfile::normalize((array) ($data['processing'] ?? []));
        $requestedActive = (bool) ($data['is_active'] ?? true);
        $source = $sourceable->videoSources()->create([
            'type' => 'telescope',
            'url' => null,
            'file_path' => null,
            'quality' => (string) ($data['quality'] ?? 'auto'),
            'format' => (string) ($data['format'] ?? 'auto'),
            'file_size' => null,
            'is_primary' => false,
            'is_active' => false,
            'storage_target_key' => $storageTarget === 'auto' ? null : $storageTarget,
            'metadata' => [
                'provider' => 'telescope',
                'telescope_status' => 'submitting',
                'telescope_progress' => 0,
                'telegram_url' => $telegramUrl,
                'requested_active' => $requestedActive,
                'requested_primary' => (bool) ($data['is_primary'] ?? false),
                'processing' => $processing,
                'processing_contract_version' => 1,
                'asset_type' => $assetType,
                'last_message' => 'Sending import request to Teletyde.',
            ],
        ]);

        $result = $this->telebot->createTelescopeJob(
            $telegramUrl,
            $source->id,
            $storageTarget,
            [
                'asset_type' => $assetType,
                'sourceable_type' => $sourceable::class,
                'sourceable_id' => $sourceable->getKey(),
                'title' => (string) ($sourceable->title ?? ''),
            ],
            $processing,
        );

        $source = $source->fresh();
        $metadata = (array) ($source->metadata ?? []);
        if (! ($result['ok'] ?? false)) {
            $source->update([
                'metadata' => array_merge($metadata, [
                    'telescope_status' => 'failed',
                    'last_error' => (string) ($result['error'] ?? 'Teletyde did not accept the Telescope import.'),
                    'last_message' => 'Telescope submission failed before queueing.',
                ]),
            ]);

            throw new \RuntimeException((string) ($result['error'] ?? 'Teletyde did not accept the Telescope import.'));
        }

        $metadata['telescope_job_id'] = (string) $result['job_id'];
        $metadata['submitted_at'] = now()->toIso8601String();
        if (in_array((string) ($metadata['telescope_status'] ?? ''), ['', 'submitting', 'queued'], true)) {
            $metadata['telescope_status'] = 'queued';
            $metadata['last_message'] = 'Waiting in queue.';
        }
        $source->update([
            'processing_job_id' => (string) $result['job_id'],
            'metadata' => $metadata,
        ]);

        return $source->fresh();
    }

    public function sync(VideoSource $source): VideoSource
    {
        $jobId = (string) ($source->processing_job_id ?: data_get($source->metadata, 'telescope_job_id'));
        if ($jobId === '') {
            throw new \RuntimeException('This Telescope source has no Teletyde job ID.');
        }

        $response = $this->telebot->telescopeJobStatus($jobId);
        if (! ($response['ok'] ?? false)) {
            throw new \RuntimeException((string) ($response['error'] ?? 'Could not read Telescope job status.'));
        }

        return $this->applyStatus($source, (array) ($response['data'] ?? []));
    }

    public function retry(VideoSource $source): VideoSource
    {
        $jobId = (string) ($source->processing_job_id ?: data_get($source->metadata, 'telescope_job_id'));
        $response = $this->telebot->retryTelescopeJob($jobId);
        if (! ($response['ok'] ?? false)) {
            throw new \RuntimeException((string) ($response['error'] ?? 'Telescope retry was not accepted.'));
        }

        $metadata = array_merge((array) $source->metadata, [
            'telescope_status' => 'queued',
            'telescope_progress' => 0,
            'last_error' => null,
            'last_message' => 'Telescope retry accepted by Teletyde.',
            'telescope_attempt' => (int) data_get($response, 'data.attempt', (int) data_get($source->metadata, 'telescope_attempt', 0) + 1),
            'stage_progress' => 0,
            'retry_requested_at' => now()->toIso8601String(),
        ]);
        $source->update(['is_active' => false, 'metadata' => $metadata]);

        return $source->fresh();
    }

    public function cancel(VideoSource $source): VideoSource
    {
        $jobId = (string) ($source->processing_job_id ?: data_get($source->metadata, 'telescope_job_id'));
        $response = $this->telebot->cancelTelescopeJob($jobId);
        if (! ($response['ok'] ?? false)) {
            throw new \RuntimeException((string) ($response['error'] ?? 'Telescope cancellation was not accepted.'));
        }

        return $this->applyStatus($source, array_merge((array) ($response['data'] ?? []), [
            'job_id' => $jobId,
            'status' => (string) data_get($response, 'data.status', 'cancelled'),
        ]));
    }

    public function destroy(VideoSource $source): VideoSource
    {
        $jobId = (string) ($source->processing_job_id ?: data_get($source->metadata, 'telescope_job_id'));
        if ($jobId === '') {
            throw new \RuntimeException('This Telescope source has no Teletyde job ID.');
        }

        $response = $this->telebot->destroyTelescopeJob($jobId);
        if (! ($response['ok'] ?? false)) {
            throw new \RuntimeException((string) ($response['error'] ?? 'Telescope temporary storage could not be cleared.'));
        }

        $metadata = array_merge((array) $source->metadata, [
            'temporary_storage_cleared_at' => now()->toIso8601String(),
            'last_message' => 'Teletyde temporary storage cleared. Remote output was preserved.',
        ]);
        $source->update(['metadata' => $metadata]);

        return $source->fresh();
    }

    public function applyEvent(VideoSource $source, array $event): VideoSource
    {
        return $this->applyStatus($source, [
            'job_id' => $event['job_id'] ?? $source->processing_job_id,
            'status' => $event['status'] ?? $event['event_type'] ?? 'queued',
            'progress' => $event['progress'] ?? 0,
            'bytes_total' => $event['bytes_total'] ?? 0,
            'bytes_transferred' => $event['bytes_transferred'] ?? 0,
            'file_name' => $event['file_name'] ?? null,
            'mime_type' => $event['mime_type'] ?? null,
            'last_error' => $event['error'] ?? null,
            'result' => $event['storage'] ?? null,
            ...array_intersect_key($event, array_flip([
                'attempt', 'stage', 'stage_progress', 'download_progress', 'processing_progress', 'upload_progress',
                'elapsed_seconds', 'eta_seconds', 'metrics', 'processing_result', 'processing', 'output_metadata', 'occurred_at',
            ])),
        ]);
    }

    private function applyStatus(VideoSource $source, array $job): VideoSource
    {
        return DB::transaction(function () use ($source, $job): VideoSource {
            $locked = VideoSource::query()->lockForUpdate()->findOrFail($source->id);

            return $this->applyLockedStatus($locked, $job);
        });
    }

    private function applyLockedStatus(VideoSource $source, array $job): VideoSource
    {
        $status = (string) ($job['status'] ?? 'queued');
        $result = (array) ($job['result'] ?? []);
        $currentStatus = (string) data_get($source->metadata, 'telescope_status', '');
        $currentAttempt = (int) data_get($source->metadata, 'telescope_attempt', 0);
        $attempt = (int) ($job['attempt'] ?? $currentAttempt);
        if ($attempt < $currentAttempt) {
            return $source;
        }
        $newAttempt = $attempt > $currentAttempt;
        $terminalStatuses = ['ready', 'failed', 'cancelled'];
        if (! $newAttempt && in_array($currentStatus, $terminalStatuses, true) && $status !== $currentStatus) {
            return $source->fresh();
        }

        $statusOrder = [
            'submitting' => 0,
            'queued' => 1,
            'waiting_for_slot' => 2,
            'resolving' => 3,
            'waiting_for_space' => 3,
            'transferring' => 4,
            'downloading' => 4,
            'probing' => 5,
            'inspecting' => 5,
            'processing' => 6,
            'preparing_mp4' => 6,
            'converting_audio' => 6,
            'optimizing_video' => 6,
            'validating' => 7,
            'uploading' => 8,
            'verifying' => 9,
            'callback_pending' => 10,
            'ready' => 11,
        ];
        if (! $newAttempt && isset($statusOrder[$currentStatus], $statusOrder[$status])
            && $statusOrder[$status] < $statusOrder[$currentStatus]) {
            return $source->fresh();
        }

        $progress = max(
            $newAttempt ? 0 : (int) data_get($source->metadata, 'telescope_progress', 0),
            min(100, max(0, (int) ($job['progress'] ?? 0))),
        );
        $metadata = array_merge((array) $source->metadata, [
            'telescope_job_id' => (string) ($job['job_id'] ?? $source->processing_job_id),
            'telescope_status' => $status,
            'telescope_attempt' => $attempt,
            'telescope_progress' => $progress,
            'bytes_total' => (int) ($job['bytes_total'] ?? 0),
            'bytes_transferred' => (int) ($job['bytes_transferred'] ?? 0),
            'last_error' => $job['last_error'] ?? null,
            'last_message' => $this->statusMessage($status),
            'telescope_last_sync_at' => now()->toIso8601String(),
        ]);

        foreach (['stage', 'stage_progress', 'download_progress', 'processing_progress', 'upload_progress', 'elapsed_seconds', 'eta_seconds', 'output_metadata'] as $key) {
            if (array_key_exists($key, $job)) {
                $metadata[$key] = $job[$key];
            }
        }
        if (is_array($job['processing'] ?? null)) {
            $metadata['effective_processing'] = $job['processing'];
        }
        $processingResult = $job['processing_result'] ?? $job['metrics'] ?? null;
        if (is_array($processingResult) && $processingResult !== []) {
            $metadata['processing_result'] = $processingResult;
            $metrics = array_merge($processingResult, [
                'processing_method' => $processingResult['method'] ?? null,
                'processing_duration' => $processingResult['duration_seconds'] ?? null,
            ]);
            $sourceSize = (int) ($metrics['source_size'] ?? 0);
            $outputSize = (int) ($metrics['output_size'] ?? 0);
            if ($sourceSize > 0 && $outputSize > 0) {
                $metrics['bytes_saved'] = $sourceSize - $outputSize;
                $metrics['percentage_saved'] = round(100 * ($sourceSize - $outputSize) / $sourceSize, 2);
            }
            $metadata['metrics'] = $metrics;
            $metadata['output_metadata'] = $processingResult['output_probe'] ?? $metadata['output_metadata'] ?? [];
        }

        $updates = ['metadata' => $metadata];
        if ($status === 'ready') {
            if (($metadata['processing_contract_version'] ?? null) === 1
                && (data_get($metadata, 'processing_result.validation.passed') !== true
                    || (int) data_get($metadata, 'processing_result.output_size', 0) !== (int) ($result['bytes'] ?? -1))) {
                throw new \RuntimeException('Telescope completion requires a validated final media result matching the stored object size.');
            }
            $publicUrl = trim((string) ($result['public_url'] ?? ''));
            if (! filter_var($publicUrl, FILTER_VALIDATE_URL)) {
                throw new \RuntimeException('Telescope reported a ready object without a valid public URL.');
            }
            $format = strtolower((string) pathinfo(parse_url($publicUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
            $output = (array) ($metadata['output_metadata'] ?? []);
            $updates = array_merge($updates, [
                'url' => $publicUrl,
                'file_path' => null,
                'format' => $format ?: ((string) ($source->format ?: 'mp4')),
                'file_size' => (int) ($result['bytes'] ?? $job['bytes_total'] ?? 0) ?: null,
                'duration_seconds' => isset($output['duration_seconds']) ? (int) round((float) $output['duration_seconds']) : $source->duration_seconds,
                'storage_disk' => (string) ($result['storage_target'] ?? ''),
                'storage_bucket' => (string) ($result['bucket'] ?? ''),
                'storage_object_key' => (string) ($result['object_key'] ?? ''),
                'storage_target_key' => (string) ($result['storage_target'] ?? '') ?: null,
                'health_status' => 'healthy',
                'verified_at' => now(),
                'is_active' => (bool) ($metadata['requested_active'] ?? true),
            ]);
        } elseif ($status === 'failed' || $status === 'cancelled') {
            $updates['is_active'] = false;
        }

        $source->update($updates);
        if ($status === 'ready' && (bool) ($metadata['requested_primary'] ?? $source->is_primary)) {
            app(MediaSourceSelectionService::class)->promoteIfHealthy($source, 'telescope_validated_output');
        }

        return $source->fresh();
    }

    private function statusMessage(string $status): string
    {
        return match ($status) {
            'queued' => 'Waiting in queue.',
            'waiting_for_slot' => 'Waiting in queue for an available transfer slot.',
            'resolving' => 'Resolving Telegram channel and message.',
            'waiting_for_space' => 'Waiting for enough temporary disk space.',
            'transferring', 'downloading' => 'Downloading Telegram media.',
            'probing', 'inspecting' => 'Inspecting media streams and selecting the fastest safe operation.',
            'preparing_mp4' => 'Preparing MP4 while preserving compatible streams.',
            'converting_audio' => 'Preserving video and converting audio for playback.',
            'processing', 'optimizing_video' => 'Optimizing video with the selected quality profile.',
            'validating' => 'Checking the completed media and its duration before upload.',
            'uploading' => 'Uploading validated media into object storage.',
            'verifying' => 'Verifying the stored object.',
            'callback_pending' => 'Object ready; Portal callback delivery is pending.',
            'ready' => 'Validated Telegram media is ready in object storage.',
            'failed' => 'Telescope import failed. Review the error and retry.',
            'cancelled' => 'Telescope import was cancelled.',
            default => ucfirst(str_replace('_', ' ', $status)).'.',
        };
    }
}
