<?php

namespace App\Services;

use App\Models\VideoSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NbxVideoSourceService
{
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
        $jobId = (string) ($metadata['nbx_job_id'] ?? $metadata['nbx']['job_id'] ?? '');
        $query = $jobId !== ''
            ? ['job_id' => $jobId]
            : ['source_url' => (string) ($source->url ?: $source->file_path ?: '')];

        $response = app(NbxEngineClientService::class)->discover($query);
        if (! ($response['ok'] ?? false)) {
            $source->update([
                'metadata' => array_merge($metadata, [
                    'fetch_status' => 'failed',
                    'last_message' => (string) ($response['error'] ?? 'NBX discovery failed.'),
                    'last_synced_at' => now()->toDateTimeString(),
                ]),
            ]);

            return $source->fresh();
        }

        return $this->upsertFromDiscoveryPayload($source->sourceable, $response['data'] ?? [], [
            'record_id' => $source->id,
            'is_primary' => $source->is_primary,
            'is_active' => $source->is_active,
            'metadata' => $metadata,
        ]);
    }

    public function upsertFromDiscoveryPayload(Model $sourceable, array $payload, array $formData = []): VideoSource
    {
        $playback = is_array($payload['playback'] ?? null) ? $payload['playback'] : [];
        $existingMetadata = (array) ($formData['metadata'] ?? []);
        $sources = is_array($payload['sources'] ?? null) ? $payload['sources'] : [];
        $storageTarget = (string) ($payload['storage_target'] ?? $payload['metadata']['nbx']['storage_target'] ?? $existingMetadata['nbx']['storage_target'] ?? 'contabo');
        $hlsUrl = $this->nbxPublicUrl(
            $payload['hls_master_url'] ?? $playback['hls_master_url'] ?? $this->sourceUrl($sources, ['hls', 'hls_master']) ?? $existingMetadata['hls_master_url'] ?? null,
            $storageTarget,
        );
        $mp4Url = $this->nbxPublicUrl(
            $payload['faststart_mp4_url'] ?? $playback['mp4_play_url'] ?? $playback['mp4_url'] ?? $this->sourceUrl($sources, ['faststart']) ?? $existingMetadata['mp4_play_url'] ?? $existingMetadata['mp4_url'] ?? null,
            $storageTarget,
        );
        $originalUrl = $this->nbxPublicUrl(
            $payload['original_url'] ?? $this->sourceUrl($sources, ['original']) ?? $existingMetadata['original_url'] ?? null,
            $storageTarget,
        );
        $downloadUrl = $this->mp4Only($this->nbxPublicUrl(
            $payload['download_mp4_url'] ?? $playback['download_url'] ?? $this->sourceUrl($sources, ['faststart'], true) ?? $this->sourceUrl($sources, ['original'], true) ?? $existingMetadata['download_url'] ?? $mp4Url ?? $originalUrl,
            $storageTarget,
        ));
        $playbackUrl = $hlsUrl ?: ($mp4Url ?: $originalUrl);
        $hasUsableSource = $this->hasUsableVideoUrl($hlsUrl, $mp4Url, $downloadUrl, $originalUrl);
        $jobId = $this->nonEmpty($payload['nbx_job_id'] ?? null);
        $status = (string) ($payload['status'] ?? 'pending');
        if ($playbackUrl && ! in_array($status, ['completed', 'partially_completed', 'failed'], true)) {
            $status = $hlsUrl ? 'completed' : 'partially_completed';
        }
        $qualities = is_array($payload['qualities'] ?? null) ? $payload['qualities'] : (is_array($playback['qualities'] ?? null) ? $playback['qualities'] : []);

        $metadata = array_merge($existingMetadata, [
            'provider' => 'nbx_engine',
            'fetch_status' => $status === 'completed' ? 'completed' : ($status === 'partially_completed' ? 'partially_completed' : $status),
            'last_message' => $payload['failure_reason'] ?? ('NBX Engine status: ' . $status),
            'nbx_job_id' => $jobId,
            'cdn_asset_id' => $payload['asset_id'] ?? null,
            'cdn_source_id' => $payload['source_id'] ?? null,
            'playback_type' => $hlsUrl ? 'hls' : 'mp4',
            'hls_master_url' => $hlsUrl,
            'mp4_play_url' => $mp4Url,
            'mp4_url' => $mp4Url,
            'download_url' => $downloadUrl,
            'original_url' => $originalUrl,
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
                ->where('type', 'nbx-engine')
                ->where('metadata->nbx_job_id', $jobId)
                ->where(function ($query): void {
                    $query->whereNull('metadata->source_role')
                        ->orWhere('metadata->source_role', '!=', 'hls_master');
                })
                ->first();
        }

        $mp4PlaybackUrl = $mp4Url ?: $originalUrl;
        $mainUrl = $mp4PlaybackUrl ?: $hlsUrl;
        $mainIsHlsOnly = ! $mp4PlaybackUrl && $hlsUrl;

        $data = [
            'type' => 'nbx-engine',
            'url' => $mainUrl,
            'file_path' => $mainUrl,
            'quality' => $mainIsHlsOnly ? 'auto' : '480p',
            'format' => $mainIsHlsOnly ? 'm3u8' : 'mp4',
            'file_size' => isset($payload['file_size_bytes']) ? (int) $payload['file_size_bytes'] : null,
            'duration_seconds' => isset($payload['duration_seconds']) ? (int) $payload['duration_seconds'] : null,
            'is_primary' => (bool) ($formData['is_primary'] ?? false),
            'is_active' => $hasUsableSource,
            'metadata' => array_merge($metadata, ['source_role' => $mainIsHlsOnly ? 'hls_master' : 'mp4_primary']),
        ];

        if ($record) {
            $record->update($data);
            $record = $record->fresh();
        } else {
            $record = $sourceable->videoSources()->create($data);
        }

        if ($hlsUrl && ! $mainIsHlsOnly) {
            $this->upsertHlsSibling($sourceable, $record, $hlsUrl, $metadata);
        }

        return $record;
    }

    public function handleWebhookPayload(array $payload, ?string $event = null, ?string $jobId = null): VideoSource
    {
        $sourcePayload = is_array($payload['source'] ?? null) ? $payload['source'] : $payload;
        $sourceJobId = $this->nonEmpty($sourcePayload['nbx_job_id'] ?? $sourcePayload['job_id'] ?? $payload['nbx_job_id'] ?? $payload['job_id'] ?? $jobId);
        if (! $sourceJobId) {
            throw new \RuntimeException('NBX webhook payload is missing a job id.');
        }

        $record = VideoSource::query()
            ->where('type', 'nbx-engine')
            ->where('metadata->nbx_job_id', $sourceJobId)
            ->first();

        $sourceable = $record?->sourceable ?: $this->sourceableFromWebhookPayload($payload);
        if (! $sourceable) {
            throw new \RuntimeException('NBX webhook could not be matched to a portal video source.');
        }

        $source = $this->upsertFromDiscoveryPayload($sourceable, $sourcePayload, [
            'record_id' => $record?->id,
            'is_primary' => $record?->is_primary ?? false,
            'is_active' => $record?->is_active ?? true,
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

    private function jobPayload(Model $sourceable, array $data, string $assetType): array
    {
        $payload = [
            'title' => (string) ($sourceable->title ?? ucfirst($assetType) . ' ' . $sourceable->getKey()),
            'asset_type' => $assetType === 'episode' ? 'episode' : 'movie',
            'description' => (string) ($sourceable->description ?? ''),
            'visibility' => 'public',
            'import_mode' => (string) ($data['import_mode'] ?? 'queue'),
            'storage_target' => (string) ($data['nbx_storage_target'] ?? 'contabo'),
            'faststart' => (bool) ($data['nbx_faststart'] ?? true),
            'compress_enabled' => (bool) ($data['nbx_compress_enabled'] ?? false),
            'hls_480p' => (bool) ($data['nbx_hls_480p'] ?? true),
            'hls_720p' => (bool) ($data['nbx_hls_720p'] ?? false),
            'hls_1080p' => (bool) ($data['nbx_hls_1080p'] ?? false),
            'allow_downloads' => (bool) ($data['nbx_allow_downloads'] ?? true),
            'allow_hls_streaming' => (bool) ($data['nbx_allow_hls_streaming'] ?? true),
            'video_ref_type' => $sourceable::class,
            'video_ref_id' => (string) $sourceable->getKey(),
        ];

        $callbackUrl = $this->callbackUrl();
        if ($callbackUrl !== '') {
            $payload['callback_url'] = $callbackUrl;
        }

        return $payload;
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
            $baseUrl = 'https://' . substr($baseUrl, 7);
        }

        return $baseUrl . '/api/v1/nbx/webhook';
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


    private function upsertHlsSibling(Model $sourceable, VideoSource $mainSource, string $hlsUrl, array $metadata): void
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
            'file_size' => null,
            'duration_seconds' => $mainSource->duration_seconds,
            'is_primary' => false,
            'is_active' => true,
            'metadata' => $hlsMetadata,
        ];

        if ($sibling) {
            $sibling->update($data);
            return;
        }

        $sourceable->videoSources()->create($data);
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
        return str_ends_with($path, '.m3u8') ? null : $url;
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
