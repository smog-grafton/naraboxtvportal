<?php

namespace App\Services;

use App\Models\CreatorContentSubmission;
use App\Models\CreatorUploadSession;
use App\Models\VideoSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NbxVideoSourceService
{
    public function __construct(private readonly UserNotificationService $notifications) {}

    public function registerDirectStorageSource(VideoSource $source): array
    {
        $this->ensureConfigured();
        $metadata = (array) ($source->metadata ?? []);
        $objectKey = $this->nonEmpty($source->storage_object_key ?? $metadata['object_key'] ?? null);
        if (! $objectKey) {
            throw new \RuntimeException('The direct Contabo source is missing its object key.');
        }
        $disk = (string) ($source->storage_disk ?: 'contabo');
        try {
            $exists = Storage::disk($disk)->exists($objectKey);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('The storage object could not be verified. Check the storage connection and retry.', 0, $exception);
        }
        if (! $exists) {
            throw new \RuntimeException('The storage object was not found. Its URL was not generated.');
        }
        $objectUrl = $this->nonEmpty($metadata['public_url'] ?? $source->url ?? $source->file_path ?? null);
        if (! $objectUrl) {
            $objectUrl = $disk === app(ContaboObjectStorageService::class)->diskName()
                ? app(ContaboObjectStorageService::class)->publicUrl($objectKey)
                : $this->nonEmpty(Storage::disk($disk)->url($objectKey));
        }
        if (! $objectUrl) {
            throw new \RuntimeException('The object exists, but its public URL could not be generated.');
        }

        $response = app(NbxEngineClientService::class)->registerStorageReference([
            'idempotency_key' => hash('sha256', implode('|', [
                'portal-storage-reference-v1',
                (string) $source->id,
                $objectKey,
            ])),
            'portal_source_id' => $source->id,
            'portal_sourceable_type' => $source->sourceable_type,
            'portal_sourceable_id' => $source->sourceable_id,
            'storage_disk' => $disk,
            'storage_bucket' => $source->storage_bucket ?: ($metadata['bucket'] ?? null),
            'object_key' => $objectKey,
            'object_url' => $objectUrl,
            'media_role' => $source->media_role ?: ($metadata['source_role'] ?? 'playback_progressive'),
            'is_primary' => (bool) $source->is_primary,
            'is_active' => (bool) $source->is_active,
            'health_status' => $source->health_status ?: 'unknown',
        ]);

        if (! ($response['ok'] ?? false)) {
            throw new \RuntimeException((string) ($response['error'] ?? 'NBX could not register the Contabo object.'));
        }

        $registration = (array) ($response['data'] ?? []);
        $metadata['nbx_storage_reference'] = [
            'id' => $registration['id'] ?? null,
            'registered_at' => now()->toIso8601String(),
        ];
        $metadata['public_url'] = $objectUrl;
        $metadata['storage_verified_at'] = now()->toIso8601String();
        VideoSource::withoutEvents(fn () => $source->forceFill([
            'url' => $source->url ?: $objectUrl,
            'file_path' => $source->file_path ?: $objectUrl,
            'nbx_asset_id' => $registration['media_asset_id'] ?? $source->nbx_asset_id,
            'verified_at' => now(),
            'metadata' => $metadata,
        ])->save());

        return $registration;
    }

    public function submitRemote(Model $sourceable, array $data, string $assetType): VideoSource
    {
        $this->ensureConfigured();

        $payload = $this->jobPayload($sourceable, $data, $assetType);
        $payload['source_url'] = trim((string) ($data['url'] ?? ''));

        if ($payload['source_url'] === '') {
            throw new \RuntimeException('Please enter a remote video URL for NBX Engine.');
        }

        $response = app(NbxEngineClientService::class)->createRemoteJob($payload);
        if (! ($response['ok'] ?? false)) {
            throw new \RuntimeException((string) ($response['error'] ?? 'NBX Engine remote fetch was not accepted.'));
        }

        return $this->upsertFromDiscoveryPayload($sourceable, $response['data'] ?? [], $data);
    }

    public function submitTelegram(Model $sourceable, array $data, string $assetType): VideoSource
    {
        $this->ensureConfigured();

        $payload = $this->jobPayload($sourceable, $data, $assetType);
        $payload['telegram_url'] = trim((string) ($data['url'] ?? ''));
        if (! preg_match('~^https://t\.me/~i', $payload['telegram_url'])) {
            throw new \RuntimeException('Please enter a valid Telegram message URL for Tele-OB.');
        }

        $response = app(NbxEngineClientService::class)->createTelegramJob($payload);
        if (! ($response['ok'] ?? false)) {
            throw new \RuntimeException((string) ($response['error'] ?? 'NBX Telegram import was not accepted.'));
        }

        $data['portal_source_type'] = 'tele_ob';

        return $this->upsertFromDiscoveryPayload($sourceable, $response['data'] ?? [], $data);
    }

    public function runExplicitAction(VideoSource $source, string $operation, array $options = []): VideoSource
    {
        $this->ensureConfigured();
        $metadata = (array) ($source->metadata ?? []);
        $jobId = (string) ($metadata['nbx_job_id'] ?? $metadata['nbx']['job_id'] ?? '');
        if ($jobId === '') {
            throw new \RuntimeException('This source does not have an NBX job ID.');
        }

        $revision = max(1, (int) ($metadata['processing_config']['revision'] ?? 1));
        $idempotencyScope = $operation === 'reconcile'
            ? now()->format('Y-m-d-H-i')
            : (string) $revision;
        $idempotencyKey = hash('sha256', implode('|', [
            'portal',
            (string) $source->id,
            $operation,
            $idempotencyScope,
            hash('sha256', json_encode($options, JSON_UNESCAPED_SLASHES) ?: ''),
        ]));
        $response = $operation === 'delete_original'
            ? app(NbxEngineClientService::class)->deleteOriginal($jobId, $idempotencyKey)
            : app(NbxEngineClientService::class)->runAction($jobId, $operation, array_merge($options, [
                'idempotency_key' => $idempotencyKey,
            ]));

        if (! ($response['ok'] ?? false)) {
            throw new \RuntimeException((string) ($response['error'] ?? 'NBX action was not accepted.'));
        }

        return $this->upsertFromDiscoveryPayload($source->sourceable, $response['data'] ?? [], [
            'record_id' => $source->id,
            'portal_source_type' => $source->type,
            'is_primary' => $source->is_primary,
            'is_active' => (bool) ($metadata['desired_is_active'] ?? $source->is_active),
            'metadata' => $metadata,
        ]);
    }

    public function updateMetadataOnly(VideoSource $source, array $data): VideoSource
    {
        $metadata = (array) ($source->metadata ?? []);
        $metadata['desired_is_active'] = (bool) ($data['is_active'] ?? $metadata['desired_is_active'] ?? $source->is_active);
        $lastRequest = (array) ($metadata['processing_config']['last_request'] ?? []);
        $draft = $this->processingRequestFromForm($data);
        if ($draft !== [] && $draft !== $this->processingRequestFromStored($lastRequest)) {
            $metadata['processing_config']['draft'] = $draft;
            $metadata['processing_config']['draft_requires_reprocess'] = true;
            $metadata['processing_config']['draft_saved_at'] = now()->toIso8601String();
        }

        $requestedPrimary = (bool) ($data['is_primary'] ?? $source->is_primary);
        $source->update([
            'quality' => (string) ($data['quality'] ?? $source->quality ?? 'auto'),
            'format' => (string) ($data['format'] ?? $source->format ?? 'mp4'),
            'file_size' => isset($data['file_size']) ? (int) $data['file_size'] : $source->file_size,
            'duration_seconds' => isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : $source->duration_seconds,
            'is_primary' => $requestedPrimary ? $source->is_primary : false,
            'is_active' => (bool) ($data['is_active'] ?? $source->is_active),
            'metadata' => $metadata,
        ]);

        $source = $source->fresh();
        if ($requestedPrimary && $source->is_active) {
            $isHls = $source->media_role === 'hls_master'
                || in_array(strtolower((string) $source->format), ['hls', 'm3u8'], true)
                || str_ends_with(strtolower((string) parse_url((string) $source->url, PHP_URL_PATH)), '.m3u8');
            if ($isHls && $source->health_status !== 'healthy') {
                app(MediaSourceHealthChecker::class)->check($source);
                $source->refresh();
            }
            if ($isHls) {
                app(MediaSourceSelectionService::class)->promoteIfHealthy($source, 'portal_metadata_edit');
            } else {
                app(MediaSourceSelectionService::class)->promote($source, 'portal_metadata_edit', false);
            }
        }

        return $source->fresh();
    }

    public function hydrateProcessingForm(VideoSource $source): array
    {
        $metadata = (array) ($source->metadata ?? []);
        $request = (array) ($metadata['processing_config']['draft']
            ?? $metadata['processing_config']['last_request']
            ?? $metadata['nbx']['requested']
            ?? []);

        return [
            'url' => $metadata['processing_config']['input']['source_url']
                ?? $metadata['source_url']
                ?? $metadata['telegram_url']
                ?? $source->url,
            'nbx_storage_target' => $request['storage_target'] ?? $metadata['nbx']['storage_target'] ?? 'contabo',
            'nbx_faststart' => (bool) ($request['faststart'] ?? true),
            'nbx_compress_enabled' => (bool) ($request['compression'] ?? $request['compress_enabled'] ?? false),
            'nbx_hls_480p' => (bool) ($request['hls']['480p'] ?? $request['hls_480p'] ?? false),
            'nbx_hls_720p' => (bool) ($request['hls']['720p'] ?? $request['hls_720p'] ?? false),
            'nbx_hls_1080p' => (bool) ($request['hls']['1080p'] ?? $request['hls_1080p'] ?? false),
            'nbx_allow_downloads' => (bool) ($request['allow_downloads'] ?? true),
            'nbx_allow_hls_streaming' => (bool) ($request['allow_hls_streaming'] ?? true),
            'nbx_retention_policy' => (string) ($request['retention_policy'] ?? 'optimized_only'),
            'nbx_max_resolution' => isset($request['max_resolution']) ? (string) $request['max_resolution'] : '720',
            'nbx_processing_preset' => (string) ($request['processing_preset'] ?? 'automatic'),
            'nbx_crf' => (int) ($request['crf'] ?? 23),
            'nbx_encoder_preset' => (string) ($request['encoder_preset'] ?? 'medium'),
            'nbx_audio_bitrate' => (string) ($request['audio_bitrate'] ?? '128k'),
        ];
    }

    public function submitObjectStorageBackfill(Model $sourceable, array $object, array $data, string $assetType): VideoSource
    {
        $this->ensureConfigured();

        $objectUrl = $this->nonEmpty($object['url'] ?? $object['public_url'] ?? $data['url'] ?? null);
        if (! $objectUrl) {
            throw new \RuntimeException('Contabo object URL is missing for NBX backfill.');
        }

        $payload = array_merge($this->jobPayload($sourceable, $data, $assetType), [
            'object_url' => $objectUrl,
            'source_url' => $objectUrl,
            'object_disk' => $object['disk'] ?? app(ContaboObjectStorageService::class)->diskName(),
            'object_key' => $object['key'] ?? null,
            'import_mode' => (string) ($data['import_mode'] ?? 'queue'),
            'storage_target' => (string) ($data['nbx_storage_target'] ?? 'contabo'),
            'faststart' => true,
            'hls_480p' => true,
            'hls_720p' => (bool) ($data['include_720p'] ?? $data['nbx_hls_720p'] ?? false),
            'hls_1080p' => false,
        ]);

        $response = app(NbxEngineClientService::class)->createObjectStorageJob($payload);
        if (! ($response['ok'] ?? false)) {
            throw new \RuntimeException((string) ($response['error'] ?? 'NBX Engine object-storage job was not accepted.'));
        }

        return $this->upsertFromDiscoveryPayload($sourceable, $response['data'] ?? [], [
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'metadata' => [
                'nbx_backfill' => [
                    'object_key' => $object['key'] ?? null,
                    'object_url' => $objectUrl,
                    'submitted_at' => now()->toDateTimeString(),
                ],
            ],
        ]);
    }

    public function submitUpload(Model $sourceable, array $data, string $assetType): VideoSource
    {
        $this->ensureConfigured();

        $uploadPath = (string) ($data['file_path'] ?? '');
        if ($uploadPath === '' || str_starts_with($uploadPath, 'http://') || str_starts_with($uploadPath, 'https://')) {
            throw new \RuntimeException('Please upload a local file before sending it to NBX Engine.');
        }

        $payload = $this->jobPayload($sourceable, $data, $assetType);
        $response = app(NbxEngineClientService::class)->uploadFromStoragePath('public', $uploadPath, $payload);
        if (! ($response['ok'] ?? false)) {
            throw new \RuntimeException((string) ($response['error'] ?? 'NBX Engine upload was not accepted.'));
        }

        Storage::disk('public')->delete($uploadPath);

        return $this->upsertFromDiscoveryPayload($sourceable, $response['data'] ?? [], $data);
    }

    public function initDirectUploadSession(Model $sourceable, array $data, string $assetType): array
    {
        $this->ensureConfigured();

        $filename = trim((string) ($data['filename'] ?? ''));
        if ($filename === '') {
            throw new \RuntimeException('Please provide the expected video filename for the NBX direct upload session.');
        }

        $payload = array_merge($this->jobPayload($sourceable, $data, $assetType), [
            'filename' => $filename,
            'size_bytes' => isset($data['size_bytes']) ? (int) $data['size_bytes'] : null,
            'mime_type' => $data['mime_type'] ?? null,
            'extension' => $data['extension'] ?? pathinfo($filename, PATHINFO_EXTENSION),
        ]);

        $response = app(NbxEngineClientService::class)->initUploadSession($payload);
        if (! ($response['ok'] ?? false)) {
            throw new \RuntimeException((string) ($response['error'] ?? 'NBX Engine upload session was not accepted.'));
        }

        return $response['data'] ?? [];
    }

    public function sync(VideoSource $source): VideoSource
    {
        $metadata = (array) ($source->metadata ?? []);
        $queries = [];
        $sourceId = $metadata['cdn_source_id'] ?? null;
        if (is_numeric($sourceId) && (int) $sourceId > 0) {
            $queries[] = ['source_id' => (int) $sourceId];
        }
        $jobId = $this->nonEmpty(
            $source->processing_job_id
            ?? $metadata['nbx_job_id']
            ?? $metadata['nbx']['job_id']
            ?? null
        );
        if ($jobId) {
            $queries[] = ['job_id' => $jobId];
        }
        $assetId = $this->nonEmpty(
            $source->nbx_asset_id
            ?? $metadata['cdn_asset_id']
            ?? $metadata['nbx']['asset_id']
            ?? null
        );
        if ($assetId) {
            $queries[] = ['asset_id' => $assetId];
        }
        if ($source->sourceable) {
            $queries[] = [
                'video_ref_type' => $source->sourceable::class,
                'video_ref_id' => (string) $source->sourceable->getKey(),
            ];
        }
        $sourceUrl = $this->nonEmpty(
            $metadata['processing_config']['input']['source_url']
            ?? $metadata['source_url']
            ?? $source->url
            ?? $source->file_path
            ?? null
        );
        if ($sourceUrl) {
            $queries[] = ['source_url' => $sourceUrl];
        }

        $client = app(NbxEngineClientService::class);
        $response = ['ok' => false, 'error' => 'No durable NBX identity is stored for this source.'];
        foreach ($queries as $query) {
            $response = $client->discover($query);
            if ($response['ok'] ?? false) {
                break;
            }
            // A 404 means this particular historical identity was not found;
            // continue through the remaining stable identities. Transport and
            // authorization failures will not be repaired by trying more keys.
            if ((int) ($response['status_code'] ?? 0) !== 404) {
                break;
            }
        }

        if (! ($response['ok'] ?? false)) {
            $supportReference = strtoupper(substr(hash('sha256', implode('|', [
                'portal-nbx-sync',
                (string) $source->id,
                (string) ($sourceId ?? ''),
                (string) ($jobId ?? ''),
                (string) ($assetId ?? ''),
                (string) ($response['error'] ?? ''),
            ])), 0, 12));
            $source->update([
                'metadata' => array_merge($metadata, [
                    'nbx_sync_status' => 'failed',
                    'nbx_sync_error_code' => 'PORTAL_SYNC_FAILED',
                    'nbx_sync_support_reference' => $supportReference,
                    'nbx_sync_diagnostics' => (string) ($response['error'] ?? 'NBX discovery failed.'),
                    'last_message' => 'The video is still in NBX, but Portal could not refresh its status. Retry synchronization or contact support with reference '.$supportReference.'.',
                    'last_synced_at' => now()->toDateTimeString(),
                ]),
            ]);

            return $source->fresh();
        }

        return $this->upsertFromDiscoveryPayload($source->sourceable, $response['data'] ?? [], [
            'record_id' => $source->id,
            'portal_source_type' => $source->type,
            'is_primary' => $source->is_primary,
            'is_active' => (bool) ($metadata['desired_is_active'] ?? $source->is_active),
            'metadata' => $metadata,
        ]);
    }

    public function upsertFromDiscoveryPayload(Model $sourceable, array $payload, array $formData = []): VideoSource
    {
        $playback = is_array($payload['playback'] ?? null) ? $payload['playback'] : [];
        $existingMetadata = (array) ($formData['metadata'] ?? []);
        $desiredIsActive = (bool) (
            $formData['is_active']
            ?? $existingMetadata['desired_is_active']
            ?? true
        );
        $sources = is_array($payload['sources'] ?? null) ? $payload['sources'] : [];
        $storageTarget = (string) ($payload['storage_target'] ?? $payload['metadata']['nbx']['storage_target'] ?? $existingMetadata['nbx']['storage_target'] ?? 'contabo');
        $hlsUrl = $this->nbxPublicUrl(
            $payload['hls_master_url'] ?? $playback['hls_master_url'] ?? $this->sourceUrl($sources, ['hls', 'hls_master']) ?? $existingMetadata['hls_master_url'] ?? null,
            $storageTarget,
        );
        $mp4Url = $this->mp4Only($this->nbxPublicUrl(
            $payload['faststart_mp4_url'] ?? $playback['mp4_play_url'] ?? $playback['mp4_url'] ?? $this->sourceUrl($sources, ['playback_progressive', 'faststart']) ?? $existingMetadata['mp4_play_url'] ?? $existingMetadata['mp4_url'] ?? null,
            $storageTarget,
        ));
        $originalUrl = $this->nbxPublicUrl(
            $payload['original_url'] ?? $this->sourceUrl($sources, ['source_original', 'original']) ?? $existingMetadata['original_url'] ?? null,
            $storageTarget,
        );
        $downloadUrl = $this->mp4Only($this->nbxPublicUrl(
            $payload['download_mp4_url'] ?? $playback['download_url'] ?? $this->sourceUrl($sources, ['playback_progressive', 'faststart'], true) ?? $existingMetadata['download_url'] ?? $mp4Url,
            $storageTarget,
        ));
        $originalMp4Url = $this->mp4Only($originalUrl);
        $playbackUrl = $hlsUrl ?: ($mp4Url ?: $originalMp4Url);
        $hasUsableSource = $this->hasUsableVideoUrl($hlsUrl, $mp4Url, $downloadUrl, $originalMp4Url);
        $jobId = $this->nonEmpty(
            $payload['nbx_job_id']
            ?? $formData['processing_job_id']
            ?? $existingMetadata['nbx_job_id']
            ?? $existingMetadata['nbx']['job_id']
            ?? null
        );
        $status = (string) ($payload['status'] ?? 'pending');
        $processingComplete = (bool) ($payload['processing_complete'] ?? $payload['metadata']['nbx']['processing_complete'] ?? false);
        if ($processingComplete && $status === 'failed') {
            $status = 'publication_pending';
        }
        if ($playbackUrl && ! in_array($status, ['completed', 'partially_completed', 'failed'], true)) {
            $status = $hlsUrl ? 'completed' : 'partially_completed';
        }
        $qualities = is_array($payload['qualities'] ?? null) ? $payload['qualities'] : (is_array($playback['qualities'] ?? null) ? $playback['qualities'] : []);

        $request = is_array($payload['metadata']['nbx']['requested'] ?? null)
            ? $payload['metadata']['nbx']['requested']
            : (array) ($existingMetadata['processing_config']['last_request'] ?? []);
        $processingConfig = array_merge((array) ($existingMetadata['processing_config'] ?? []), [
            'schema_version' => 1,
            'revision' => max(1, (int) ($payload['processing_revision'] ?? $payload['metadata']['nbx']['processing_revision'] ?? 1)),
            'input' => [
                'source_url' => $payload['metadata']['telegram_url']
                    ?? $payload['metadata']['object_url']
                    ?? $payload['metadata']['source_url']
                    ?? $payload['metadata']['nbx']['source_url']
                    ?? $existingMetadata['processing_config']['input']['source_url']
                    ?? $existingMetadata['source_url']
                    ?? null,
                'filename' => $payload['metadata']['original_filename'] ?? $payload['input']['filename'] ?? null,
                'container' => $payload['probe']['container'] ?? null,
                'mime_type' => $payload['mime_type'] ?? null,
            ],
            'last_request' => $request,
            'last_status' => $status,
            'last_error' => $payload['safe_message'] ?? $payload['failure_reason'] ?? null,
            'last_processed_at' => $payload['updated_at'] ?? null,
        ]);
        if ($status === 'completed') {
            unset($processingConfig['draft'], $processingConfig['draft_requires_reprocess'], $processingConfig['draft_saved_at']);
        }

        $metadata = array_merge($existingMetadata, [
            'provider' => 'nbx_engine',
            'desired_is_active' => $desiredIsActive,
            'fetch_status' => $status === 'completed' ? 'completed' : ($status === 'partially_completed' ? 'partially_completed' : $status),
            'last_message' => $payload['safe_message'] ?? $payload['failure_reason'] ?? ('NBX Engine status: '.$status),
            'nbx_job_id' => $jobId,
            'cdn_asset_id' => $payload['asset_id'] ?? $formData['nbx_asset_id'] ?? $existingMetadata['cdn_asset_id'] ?? null,
            'cdn_source_id' => $payload['source_id'] ?? $existingMetadata['cdn_source_id'] ?? null,
            'nbx_sync_status' => 'synced',
            'nbx_sync_error_code' => null,
            'nbx_sync_support_reference' => null,
            'nbx_sync_diagnostics' => null,
            'error_code' => $payload['error_code'] ?? null,
            'support_reference' => $payload['support_reference'] ?? null,
            'retryable' => (bool) ($payload['retryable'] ?? false),
            'action_required' => (bool) ($payload['action_required'] ?? false),
            'processing_complete' => $processingComplete,
            'storage_verified' => (bool) ($payload['storage_verified'] ?? $payload['metadata']['nbx']['storage_verified'] ?? false),
            'publication_status' => $payload['publication_status'] ?? $payload['metadata']['nbx']['publication_status'] ?? null,
            'publication_error' => $payload['publication_error'] ?? $payload['metadata']['nbx']['finalization_error'] ?? null,
            'playback_type' => $hlsUrl ? 'hls' : 'mp4',
            'hls_master_url' => $hlsUrl,
            'mp4_play_url' => $mp4Url,
            'mp4_url' => $mp4Url,
            'download_url' => $downloadUrl,
            'original_url' => $originalUrl,
            'outputs' => is_array($payload['outputs'] ?? null) ? $payload['outputs'] : [],
            'original_retained' => (bool) ($payload['original_retained'] ?? ($originalUrl !== null)),
            'processing_config' => $processingConfig,
            'qualities' => $this->filterQualities($qualities),
            'probe' => is_array($payload['probe'] ?? null) ? $payload['probe'] : [],
            'nbx' => is_array($payload['metadata']['nbx'] ?? null) ? $payload['metadata']['nbx'] : [],
            'last_synced_at' => now()->toDateTimeString(),
        ]);

        $record = null;
        if (! empty($formData['record_id'])) {
            $record = VideoSource::whereKey((int) $formData['record_id'])->first();
        }
        if ($record && (($record->metadata['source_role'] ?? null) === 'hls_master')) {
            $record = null;
        }

        if (! $record && $jobId !== '') {
            $record = $sourceable->videoSources()
                ->whereIn('type', ['nbx-engine', 'tele_ob'])
                ->where('metadata->nbx_job_id', $jobId)
                ->where(function ($query): void {
                    $query->whereNull('metadata->source_role')
                        ->orWhere('metadata->source_role', '!=', 'hls_master');
                })
                ->first();
        }

        $mp4PlaybackUrl = $mp4Url ?: $originalMp4Url;
        $mainUrl = $mp4PlaybackUrl ?: $hlsUrl;
        $mainIsHlsOnly = ! $mp4PlaybackUrl && $hlsUrl;

        $recordType = (string) ($formData['portal_source_type'] ?? $record?->type ?? 'nbx-engine');
        $data = [
            'type' => in_array($recordType, ['nbx-engine', 'tele_ob'], true) ? $recordType : 'nbx-engine',
            'url' => $mainUrl,
            'file_path' => $mainUrl,
            'quality' => $mainIsHlsOnly ? 'auto' : '480p',
            'format' => $mainIsHlsOnly ? 'm3u8' : 'mp4',
            'media_role' => $mainIsHlsOnly ? 'hls_master' : 'faststart_mp4',
            'server_key' => 'nbx',
            'source_group' => $jobId !== '' ? $jobId : null,
            'quality_label' => $mainIsHlsOnly ? 'auto' : '480p',
            'file_size' => isset($payload['file_size_bytes']) ? (int) $payload['file_size_bytes'] : null,
            'duration_seconds' => isset($payload['duration_seconds']) ? (int) $payload['duration_seconds'] : null,
            // Keep the existing MP4 preference until the HLS manifest and a
            // referenced child are verified; successful verification promotes HLS.
            'is_primary' => (bool) ($formData['is_primary'] ?? false),
            'is_active' => $hasUsableSource
                && $desiredIsActive
                && ! in_array($status, ['failed', 'destroyed', 'cancelled', 'canceled'], true),
            'storage_disk' => $storageTarget,
            'storage_bucket' => $payload['storage_bucket'] ?? $payload['metadata']['nbx']['storage_bucket'] ?? null,
            'storage_object_key' => $this->sourceObjectKey($sources, $mainIsHlsOnly ? ['hls_master', 'hls'] : ['playback_progressive', 'faststart']),
            'nbx_asset_id' => $payload['asset_id'] ?? $record?->nbx_asset_id ?? $existingMetadata['cdn_asset_id'] ?? null,
            'processing_job_id' => $jobId !== '' ? $jobId : null,
            'metadata' => array_merge($metadata, ['source_role' => $mainIsHlsOnly ? 'hls_master' : 'mp4_primary']),
        ];

        if ($record) {
            if ((string) $record->url !== (string) $mainUrl) {
                $data = array_merge($data, [
                    'health_status' => 'unknown',
                    'last_health_check_at' => null,
                    'last_http_status' => null,
                    'last_health_error' => null,
                    'consecutive_failures' => 0,
                    'verified_at' => null,
                ]);
            }
            $record->update($data);
            $record = $record->fresh();
        } else {
            $record = $sourceable->videoSources()->create($data);
        }

        $hlsSource = null;
        if ($hlsUrl && ! $mainIsHlsOnly) {
            $hlsSource = $this->upsertHlsSibling($sourceable, $record, $hlsUrl, $metadata);
        } elseif ($mainIsHlsOnly) {
            $hlsSource = $record;
        }

        if ($hlsSource && $hlsSource->is_active) {
            $checker = app(MediaSourceHealthChecker::class);
            $verified = $checker->isFresh($hlsSource)
                ? $hlsSource->health_status === 'healthy' && $hlsSource->verified_at !== null
                : (bool) ($checker->check($hlsSource)['verified'] ?? false);
            if ($verified) {
                app(MediaSourceSelectionService::class)->promoteIfHealthy($hlsSource, 'nbx_verified_hls');
            }
        }

        $this->syncCreatorSubmission($sourceable, $record, $payload, $status, $hasUsableSource);

        return $record->fresh();
    }

    public function handleWebhookPayload(array $payload, ?string $event = null, ?string $jobId = null): VideoSource
    {
        $sourcePayload = is_array($payload['source'] ?? null) ? $payload['source'] : $payload;
        $sourceJobId = $this->nonEmpty($sourcePayload['nbx_job_id'] ?? $sourcePayload['job_id'] ?? $payload['nbx_job_id'] ?? $payload['job_id'] ?? $jobId);
        if (! $sourceJobId) {
            throw new \RuntimeException('NBX webhook payload is missing a job id.');
        }

        $record = VideoSource::query()
            ->whereIn('type', ['nbx-engine', 'tele_ob'])
            ->where('metadata->nbx_job_id', $sourceJobId)
            ->first();

        $sourceable = $record?->sourceable ?: $this->sourceableFromWebhookPayload($payload);
        if (! $sourceable) {
            throw new \RuntimeException('NBX webhook could not be matched to a portal video source.');
        }

        $resolvedEvent = (string) ($event ?: ($payload['event'] ?? ''));
        if ($record && in_array($resolvedEvent, [
            'storage.original_deleted',
            'storage.faststart_deleted',
            'storage.hls_deleted',
            'storage.asset_deleted',
        ], true)) {
            return $this->handleStorageDeletionWebhook($record, $resolvedEvent, $payload);
        }

        $source = $this->upsertFromDiscoveryPayload($sourceable, $sourcePayload, [
            'record_id' => $record?->id,
            'is_primary' => $record?->is_primary ?? false,
            'is_active' => (bool) (((array) ($record?->metadata ?? []))['desired_is_active'] ?? $record?->is_active ?? true),
            'metadata' => (array) ($record?->metadata ?? []),
        ]);

        $metadata = (array) ($source->metadata ?? []);
        $metadata['provider'] = 'nbx_engine';
        $metadata['nbx_job_id'] = $sourceJobId;
        $metadata['last_webhook_event'] = $event ?: ($payload['event'] ?? null);
        $metadata['last_webhook_at'] = now()->toDateTimeString();

        if (is_array($payload['skipped_profiles'] ?? null)) {
            $metadata['skipped_profiles'] = $payload['skipped_profiles'];
        }
        if ($this->nonEmpty($payload['failure_reason'] ?? null)) {
            $metadata['failure_reason'] = $payload['failure_reason'];
            $metadata['last_message'] = $payload['failure_reason'];
        }
        if (is_array($payload['context'] ?? null) && isset($payload['context']['reason'])) {
            $metadata['last_message'] = (string) $payload['context']['reason'];
        }
        if (Str::contains((string) ($event ?: $payload['event'] ?? ''), '.skipped')) {
            $metadata['last_message'] = (string) ($payload['context']['reason'] ?? 'NBX quality skipped.');
        }
        if (($event ?: $payload['event'] ?? '') === 'job.failed') {
            $metadata['fetch_status'] = 'failed';
        } elseif (($event ?: $payload['event'] ?? '') === 'job.partially_completed') {
            $metadata['fetch_status'] = 'partially_completed';
        } elseif (($event ?: $payload['event'] ?? '') === 'job.completed') {
            $metadata['fetch_status'] = 'completed';
        }

        $source->update(['metadata' => $metadata]);

        return $source->fresh();
    }

    private function syncCreatorSubmission(
        Model $sourceable,
        VideoSource $source,
        array $payload,
        string $nbxStatus,
        bool $hasUsableSource
    ): void {
        $submission = CreatorContentSubmission::query()
            ->where('content_type', $sourceable::class)
            ->where('content_id', $sourceable->getKey())
            ->latest()
            ->first();
        if (! $submission) {
            return;
        }

        $failed = in_array($nbxStatus, ['failed', 'destroyed', 'cancelled', 'canceled'], true);
        $ready = $nbxStatus === 'completed' && $hasUsableSource && $source->is_active;
        $processingStatus = $failed ? 'processing_failed' : ($ready ? 'ready' : 'processing');
        $previousStatus = $submission->processing_status;
        $progress = isset($payload['progress_percent'])
            ? max(0, min(100, (int) $payload['progress_percent']))
            : ($ready ? 100 : null);

        $submission->update([
            'processing_status' => $processingStatus,
            'cdn_asset_id' => $payload['asset_id'] ?? $source->nbx_asset_id ?? $submission->cdn_asset_id,
            'video_source_id' => $source->id,
            'processing_job_id' => $payload['nbx_job_id'] ?? $source->processing_job_id,
            'progress_percent' => $progress,
            'processing_stage' => $this->creatorFriendlyStage(
                (string) ($payload['stage'] ?? $payload['processing_stage'] ?? $nbxStatus)
            ),
            'status_message' => $ready
                ? 'NBX verified the optimized output and Portal stored the playable source.'
                : ($payload['message'] ?? null),
            'failure_reason' => $failed
                ? ($payload['safe_message'] ?? $payload['failure_reason'] ?? 'NBX processing failed.')
                : null,
            'result_metadata' => $ready ? [
                'output_format' => $source->format,
                'file_size_bytes' => $source->file_size,
                'storage_saved_bytes' => $payload['storage_saved_bytes'] ?? null,
                'warnings' => $payload['warnings'] ?? [],
            ] : array_merge((array) $submission->result_metadata, array_filter([
                'error_code' => $payload['error_code'] ?? null,
                'support_reference' => $payload['support_reference'] ?? null,
                'retryable' => isset($payload['retryable']) ? (bool) $payload['retryable'] : null,
                'action_required' => isset($payload['action_required']) ? (bool) $payload['action_required'] : null,
            ], static fn (mixed $value): bool => $value !== null)),
            'completed_at' => $ready ? now() : null,
        ]);
        CreatorUploadSession::where('submission_id', $submission->id)
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now(),
                'video_source_id' => $source->id,
            ]);
        $sourceable->forceFill(['processing_status' => $processingStatus])->save();

        if ($previousStatus !== $processingStatus && ($ready || $failed)) {
            $this->notifications->createForUser((int) $submission->user_id, [
                'title' => $ready ? 'Video processing complete' : 'Video processing needs attention',
                'message' => $ready
                    ? "{$sourceable->title} is ready for review."
                    : "{$sourceable->title} could not be processed. Open Processing to see the reason and retry safely.",
                'type' => 'creator_processing',
                'action_url' => '/creator/processing',
                'media_id' => $sourceable->getKey(),
            ]);
        }
    }

    private function handleStorageDeletionWebhook(VideoSource $record, string $event, array $payload): VideoSource
    {
        $metadata = (array) ($record->metadata ?? []);
        $jobId = (string) ($metadata['nbx_job_id'] ?? '');
        $role = match ($event) {
            'storage.original_deleted' => 'source_original',
            'storage.faststart_deleted' => 'faststart_mp4',
            'storage.hls_deleted' => 'hls_master',
            default => 'asset',
        };
        $metadata['last_webhook_event'] = $event;
        $metadata['last_webhook_at'] = now()->toDateTimeString();
        $metadata['nbx_storage_event'] = [
            'event' => $event,
            'media_role' => $role,
            'object_keys' => (array) ($payload['context']['deleted_keys'] ?? []),
            'received_at' => now()->toIso8601String(),
        ];

        if ($role === 'source_original') {
            $metadata['original_url'] = null;
            $metadata['original_retained'] = false;
            unset($metadata['nbx']['final_artifacts']['original']);
            VideoSource::withoutEvents(fn () => $record->forceFill(['metadata' => $metadata])->save());

            return $record->fresh();
        }

        $siblings = VideoSource::query()
            ->where('sourceable_type', $record->sourceable_type)
            ->where('sourceable_id', $record->sourceable_id)
            ->where('metadata->nbx_job_id', $jobId)
            ->get();

        if ($role === 'faststart_mp4') {
            foreach ($siblings as $source) {
                $sourceMetadata = (array) ($source->metadata ?? []);
                $sourceMetadata['mp4_play_url'] = null;
                $sourceMetadata['mp4_url'] = null;
                $sourceMetadata['download_url'] = null;
                unset($sourceMetadata['nbx']['final_artifacts']['faststart']);
                $changes = ['metadata' => $sourceMetadata];
                if ($source->media_role !== 'hls_master') {
                    $changes = array_merge($changes, [
                        'is_active' => false,
                        'is_primary' => false,
                        'health_status' => 'unreachable',
                        'last_health_check_at' => now(),
                        'last_health_error' => 'Fast Start object was deleted through NBX.',
                        'deleted_from_storage_at' => now(),
                    ]);
                }
                VideoSource::withoutEvents(fn () => $source->forceFill($changes)->save());
            }
        } elseif ($role === 'hls_master') {
            foreach ($siblings as $source) {
                $sourceMetadata = (array) ($source->metadata ?? []);
                $sourceMetadata['hls_master_url'] = null;
                $sourceMetadata['qualities'] = [];
                unset(
                    $sourceMetadata['nbx']['final_artifacts']['hls_master'],
                    $sourceMetadata['nbx']['final_artifacts']['qualities'],
                );
                $changes = ['metadata' => $sourceMetadata];
                if ($source->media_role === 'hls_master') {
                    $changes = array_merge($changes, [
                        'is_active' => false,
                        'is_primary' => false,
                        'health_status' => 'unreachable',
                        'last_health_check_at' => now(),
                        'last_health_error' => 'HLS package was deleted through NBX.',
                        'deleted_from_storage_at' => now(),
                    ]);
                }
                VideoSource::withoutEvents(fn () => $source->forceFill($changes)->save());
            }
        } else {
            foreach ($siblings as $source) {
                $sourceMetadata = (array) ($source->metadata ?? []);
                $sourceMetadata['hls_master_url'] = null;
                $sourceMetadata['mp4_play_url'] = null;
                $sourceMetadata['mp4_url'] = null;
                $sourceMetadata['download_url'] = null;
                $sourceMetadata['original_url'] = null;
                $sourceMetadata['qualities'] = [];
                VideoSource::withoutEvents(fn () => $source->forceFill([
                    'is_active' => false,
                    'is_primary' => false,
                    'health_status' => 'unreachable',
                    'last_health_check_at' => now(),
                    'last_health_error' => 'NBX asset was intentionally deleted.',
                    'deleted_from_storage_at' => now(),
                    'metadata' => $sourceMetadata,
                ])->save());
            }
        }

        $fallback = $record->sourceable
            ? app(MediaSourceSelectionService::class)->candidatesFor($record->sourceable)
                ->first(fn (VideoSource $source): bool => $source->health_status === 'healthy')
            : null;
        if (! $fallback && $record->sourceable) {
            $fallback = app(MediaSourceSelectionService::class)->candidatesFor($record->sourceable)
                ->first(function (VideoSource $source): bool {
                    $result = app(MediaSourceHealthChecker::class)->check($source);

                    return ($result['verified'] ?? false) === true;
                });
        }
        if ($fallback) {
            app(MediaSourceSelectionService::class)->promoteIfHealthy($fallback, 'nbx_storage_deletion');
        }

        return $record->fresh();
    }

    private function jobPayload(Model $sourceable, array $data, string $assetType): array
    {
        $processing = $this->processingRequestFromForm($data);
        $sourceIdentity = trim((string) ($data['url'] ?? $data['file_path'] ?? $data['filename'] ?? 'upload'));
        $payload = [
            'title' => (string) ($sourceable->title ?? ucfirst($assetType).' '.$sourceable->getKey()),
            'asset_type' => $assetType === 'episode' ? 'episode' : 'movie',
            'description' => (string) ($sourceable->description ?? ''),
            'visibility' => 'public',
            'import_mode' => (string) ($data['import_mode'] ?? 'queue'),
            ...$processing,
            'video_ref_type' => $sourceable::class,
            'video_ref_id' => (string) $sourceable->getKey(),
        ];
        $payload['idempotency_key'] = (string) ($data['idempotency_key'] ?? hash('sha256', implode('|', [
            'portal-nbx-ingest-v1',
            $sourceable::class,
            (string) $sourceable->getKey(),
            $sourceIdentity,
            json_encode($processing, JSON_UNESCAPED_SLASHES) ?: '',
        ])));

        $callbackUrl = $this->callbackUrl();
        if ($callbackUrl !== '') {
            $payload['callback_url'] = $callbackUrl;
        }

        return $payload;
    }

    /**
     * Keep this normalized shape identical for initial submissions, edit hydration,
     * draft comparisons and explicit reprocessing.
     */
    private function processingRequestFromForm(array $data): array
    {
        $retention = (string) ($data['nbx_retention_policy'] ?? 'optimized_only');
        if (! in_array($retention, ['optimized_only', 'retain_original'], true)) {
            $retention = 'optimized_only';
        }

        return [
            'storage_target' => (string) ($data['nbx_storage_target'] ?? 'contabo'),
            'faststart' => (bool) ($data['nbx_faststart'] ?? true),
            'compress_enabled' => (bool) ($data['nbx_compress_enabled'] ?? false),
            'hls_480p' => (bool) ($data['nbx_hls_480p'] ?? false),
            'hls_720p' => (bool) ($data['nbx_hls_720p'] ?? false),
            'hls_1080p' => (bool) ($data['nbx_hls_1080p'] ?? false),
            'allow_downloads' => (bool) ($data['nbx_allow_downloads'] ?? true),
            'allow_hls_streaming' => (bool) ($data['nbx_allow_hls_streaming'] ?? true),
            'retention_policy' => $retention,
            'retain_original' => $retention === 'retain_original',
            'processing_preset' => (string) ($data['nbx_processing_preset'] ?? 'automatic'),
            'max_resolution' => (int) ($data['nbx_max_resolution'] ?? 720),
            'crf' => max(18, min(32, (int) ($data['nbx_crf'] ?? 23))),
            'encoder_preset' => (string) ($data['nbx_encoder_preset'] ?? 'medium'),
            'audio_bitrate' => (string) ($data['nbx_audio_bitrate'] ?? '128k'),
        ];
    }

    private function creatorFriendlyStage(string $stage): string
    {
        $normalized = strtolower(trim($stage));

        return match (true) {
            str_contains($normalized, 'telegram') && str_contains($normalized, 'wait') => 'Waiting to connect to Telegram',
            str_contains($normalized, 'telegram') || str_contains($normalized, 'fetch') => 'Fetching movie',
            str_contains($normalized, 'probe') || str_contains($normalized, 'inspect') => 'Inspecting video',
            str_contains($normalized, 'compress') || str_contains($normalized, 'optim') || str_contains($normalized, 'transcod') => 'Optimizing for playback',
            str_contains($normalized, 'upload') || str_contains($normalized, 'storage') => 'Uploading final video',
            str_contains($normalized, 'verify') => 'Verifying playback',
            in_array($normalized, ['completed', 'ready'], true) => 'Ready for review',
            in_array($normalized, ['failed', 'destroyed', 'cancelled', 'canceled'], true) => 'Processing failed',
            in_array($normalized, ['pending', 'queued', 'waiting_for_capacity'], true) => 'Waiting to start',
            default => 'Processing video',
        };
    }

    private function processingRequestFromStored(array $request): array
    {
        return $this->processingRequestFromForm([
            'nbx_storage_target' => $request['storage_target'] ?? 'contabo',
            'nbx_faststart' => $request['faststart'] ?? true,
            'nbx_compress_enabled' => $request['compress_enabled'] ?? $request['compression'] ?? false,
            'nbx_hls_480p' => $request['hls_480p'] ?? $request['hls']['480p'] ?? false,
            'nbx_hls_720p' => $request['hls_720p'] ?? $request['hls']['720p'] ?? false,
            'nbx_hls_1080p' => $request['hls_1080p'] ?? $request['hls']['1080p'] ?? false,
            'nbx_allow_downloads' => $request['allow_downloads'] ?? true,
            'nbx_allow_hls_streaming' => $request['allow_hls_streaming'] ?? true,
            'nbx_retention_policy' => $request['retention_policy'] ?? 'optimized_only',
            'nbx_processing_preset' => $request['processing_preset'] ?? 'automatic',
            'nbx_max_resolution' => $request['max_resolution'] ?? '720',
            'nbx_crf' => $request['crf'] ?? 23,
            'nbx_encoder_preset' => $request['encoder_preset'] ?? 'medium',
            'nbx_audio_bitrate' => $request['audio_bitrate'] ?? '128k',
        ]);
    }

    private function callbackUrl(): string
    {
        $configured = trim((string) config('services.nbx_engine.callback_url', ''));
        if ($configured !== '') {
            return $configured;
        }

        $baseUrl = rtrim((string) config('app.url', ''), '/');
        if ($baseUrl === '') {
            return url('/api/v1/nbx/webhook');
        }

        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        if ($host !== '' && ! in_array($host, ['localhost', '127.0.0.1'], true) && str_starts_with($baseUrl, 'http://')) {
            $baseUrl = 'https://'.substr($baseUrl, 7);
        }

        return $baseUrl.'/api/v1/nbx/webhook';
    }

    private function sourceableFromWebhookPayload(array $payload): ?Model
    {
        $refType = $this->nonEmpty($payload['video_ref_type'] ?? $payload['source']['metadata']['video_ref_type'] ?? null);
        $refId = $this->nonEmpty($payload['video_ref_id'] ?? $payload['source']['metadata']['video_ref_id'] ?? null);
        if (! $refType || ! $refId) {
            return null;
        }

        $allowed = [
            \App\Models\Movie::class,
            \App\Models\Episode::class,
            \App\Models\TVShow::class,
        ];

        if (! in_array($refType, $allowed, true) || ! is_a($refType, Model::class, true)) {
            return null;
        }

        /** @var class-string<Model> $refType */
        $model = $refType::query()->find($refId);

        return $model instanceof Model && method_exists($model, 'videoSources') ? $model : null;
    }

    private function sourceUrl(array $sources, array $roles, bool $downloadableOnly = false): ?string
    {
        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }

            $role = strtolower((string) ($source['role'] ?? ''));
            $type = strtolower((string) ($source['type'] ?? ''));
            if (! in_array($role, $roles, true) && ! in_array($type, $roles, true)) {
                continue;
            }

            if ($downloadableOnly && ! (bool) ($source['is_downloadable'] ?? false)) {
                continue;
            }

            $url = $this->nonEmpty($source['url'] ?? null);
            if ($url) {
                return $url;
            }
        }

        return null;
    }

    private function hasUsableVideoUrl(?string ...$urls): bool
    {
        foreach ($urls as $url) {
            if (is_string($url) && trim($url) !== '') {
                return true;
            }
        }

        return false;
    }

    private function upsertHlsSibling(Model $sourceable, VideoSource $mainSource, string $hlsUrl, array $metadata): VideoSource
    {
        $jobId = $this->nonEmpty($metadata['nbx_job_id'] ?? null);
        $sibling = $sourceable->videoSources()
            ->where('type', 'nbx-engine')
            ->where(function ($query) use ($jobId, $hlsUrl): void {
                $query->where('url', $hlsUrl)
                    ->orWhere('file_path', $hlsUrl)
                    ->orWhere(function ($query) use ($jobId): void {
                        $query->where('metadata->source_role', 'hls_master');
                        if ($jobId) {
                            $query->where('metadata->nbx_job_id', $jobId);
                        }
                    });
            })
            ->first();

        $hlsMetadata = array_merge($metadata, [
            'source_role' => 'hls_master',
            'mp4_source_id' => $mainSource->id,
            'download_url' => null,
            'playback_type' => 'hls',
        ]);

        $data = [
            'type' => 'nbx-engine',
            'url' => $hlsUrl,
            'file_path' => $hlsUrl,
            'quality' => 'auto',
            'format' => 'm3u8',
            'media_role' => 'hls_master',
            'server_key' => 'nbx',
            'source_group' => $jobId,
            'quality_label' => 'auto',
            'file_size' => null,
            'duration_seconds' => $mainSource->duration_seconds,
            'is_primary' => false,
            'is_active' => (bool) $mainSource->is_active,
            'storage_disk' => $mainSource->storage_disk,
            'storage_bucket' => $mainSource->storage_bucket,
            'storage_object_key' => $this->objectKeyFromUrl($hlsUrl),
            'nbx_asset_id' => $mainSource->nbx_asset_id,
            'processing_job_id' => $jobId,
            'metadata' => $hlsMetadata,
        ];

        if ($sibling) {
            if ((string) $sibling->url !== $hlsUrl) {
                $data = array_merge($data, [
                    'health_status' => 'unknown',
                    'last_health_check_at' => null,
                    'last_http_status' => null,
                    'last_health_error' => null,
                    'consecutive_failures' => 0,
                    'verified_at' => null,
                ]);
            }
            $sibling->update($data);

            return $sibling->fresh();
        }

        return $sourceable->videoSources()->create($data);
    }

    private function sourceObjectKey(array $sources, array $roles): ?string
    {
        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }
            $role = strtolower((string) ($source['role'] ?? $source['type'] ?? ''));
            if (! in_array($role, $roles, true)) {
                continue;
            }
            $key = $this->nonEmpty($source['key'] ?? $source['storage_key'] ?? $source['path'] ?? null);
            if ($key) {
                return ltrim($key, '/');
            }
            $url = $this->nonEmpty($source['url'] ?? null);
            if ($url) {
                return $this->objectKeyFromUrl($url);
            }
        }

        return null;
    }

    private function objectKeyFromUrl(string $url): ?string
    {
        $base = rtrim((string) config('services.contabo_object_storage.public_url', ''), '/');
        if ($base === '' || ! str_starts_with($url, $base.'/')) {
            return null;
        }

        return rawurldecode(ltrim(substr($url, strlen($base)), '/'));
    }

    private function ensureConfigured(): void
    {
        if (! (bool) config('services.nbx_engine.enabled', false)) {
            throw new \RuntimeException('NBX Engine is disabled. Set NBX_ENGINE_ENABLED=true.');
        }
        if (trim((string) config('services.nbx_engine.base_url', '')) === '') {
            throw new \RuntimeException('NBX Engine base URL is not configured.');
        }
    }

    private function nonEmpty(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function mp4Only(mixed $value): ?string
    {
        $url = $this->nonEmpty($value);
        if (! $url) {
            return null;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return str_ends_with($path, '.mp4') ? $url : null;
    }

    private function nbxPublicUrl(mixed $value, string $storageTarget): ?string
    {
        $url = $this->nonEmpty($value);
        if (! $url) {
            return null;
        }

        if ($storageTarget === 'contabo' && $this->isNbxLocalMediaUrl($url)) {
            return null;
        }

        return $url;
    }

    private function isNbxLocalMediaUrl(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        if (! str_starts_with($path, '/media/') && ! str_starts_with($path, '/media-hls/')) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $baseHost = strtolower((string) parse_url((string) config('services.nbx_engine.base_url', ''), PHP_URL_HOST));

        return $host !== '' && $baseHost !== '' && $host === $baseHost;
    }

    private function filterQualities(array $qualities): array
    {
        return array_values(array_filter($qualities, static function (mixed $quality): bool {
            if (! is_array($quality)) {
                return false;
            }
            $id = strtolower((string) ($quality['id'] ?? $quality['label'] ?? ''));

            return ! str_contains($id, '1080') && ! str_contains($id, '4k');
        }));
    }
}
