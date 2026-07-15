<?php

namespace App\Filament\Resources\Concerns;

use App\Jobs\FetchVideoFromUrlJob;
use App\Models\VideoSource;
use App\Services\ContaboObjectStorageService;
use Illuminate\Support\Facades\Storage;

trait ManagesContaboVideoSources
{
    private function createOrUpdateContaboObjectStorageSource(?VideoSource $record, array $data, string $assetType): VideoSource
    {
        $owner = $this->getOwnerRecord();
        $service = app(ContaboObjectStorageService::class);
        $remoteUrl = trim((string) ($data['url'] ?? ''));
        $uploadPath = $this->normalizeContaboUploadState($data['file_path'] ?? $record?->file_path ?? '');
        $publicUrl = '';
        $objectKey = null;
        $fileSize = isset($data['file_size']) ? (int) $data['file_size'] : null;
        $sourceMode = 'update_only';

        if ($remoteUrl !== '') {
            if ($service->isContaboPublicUrl($remoteUrl)) {
                $publicUrl = $remoteUrl;
                $objectKey = $service->objectKeyFromPublicUrl($remoteUrl);
                $sourceMode = 'direct_url';
            } else {
                return $this->queueContaboRemoteFetch($record, $remoteUrl, $data, $assetType, $service);
            }
        } elseif ($uploadPath !== '') {
            if ($this->isRemoteVideoPath($uploadPath)) {
                if ($service->isContaboPublicUrl($uploadPath)) {
                    $publicUrl = $uploadPath;
                    $objectKey = $service->objectKeyFromPublicUrl($uploadPath);
                    $sourceMode = 'direct_url';
                } else {
                    return $this->queueContaboRemoteFetch($record, $uploadPath, $data, $assetType, $service);
                }
            } elseif ($this->contaboObjectExists($uploadPath, $service)) {
                $objectKey = $service->normalizeKey($uploadPath);
                $publicUrl = $service->publicUrl($objectKey);
                $fileSize = $this->contaboObjectSize($objectKey, $service) ?: $fileSize;
                $sourceMode = 'upload';
            } elseif (Storage::disk('public')->exists($uploadPath)) {
                $sourceMode = 'upload';
                $result = $service->uploadLocalFile(
                    Storage::disk('public')->path($uploadPath),
                    basename($uploadPath),
                    [
                        'asset_type' => $assetType,
                        'sourceable_type' => $owner::class,
                        'sourceable_id' => (int) $owner->id,
                        'quality' => (string) ($data['quality'] ?? 'auto'),
                        'format' => (string) ($data['format'] ?? 'auto'),
                    ]
                );

                if (! ($result['ok'] ?? false)) {
                    throw new \RuntimeException((string) ($result['error'] ?? 'Contabo Object Storage upload failed.'));
                }

                Storage::disk('public')->delete($uploadPath);

                $publicUrl = (string) ($result['public_url'] ?? '');
                $objectKey = isset($result['key']) ? (string) $result['key'] : null;
                $fileSize = isset($result['file_size']) ? (int) $result['file_size'] : $fileSize;
            } else {
                throw new \RuntimeException('The uploaded file could not be found on the Contabo or public disk.');
            }
        } elseif ($record) {
            $recordMetadata = (array) ($record->metadata ?? []);
            $publicUrl = (string) ($recordMetadata['public_url'] ?? $record->url ?? $record->file_path ?? '');
            $objectKey = isset($recordMetadata['object_key']) ? (string) $recordMetadata['object_key'] : $service->objectKeyFromPublicUrl($publicUrl);
        }

        if ($publicUrl === '') {
            throw new \RuntimeException('Add a Contabo public URL, a remote URL to fetch, or a video file to upload.');
        }

        $resolvedFormat = (string) ($data['format'] ?? '');
        if ($resolvedFormat === '' || $resolvedFormat === 'auto') {
            $resolvedFormat = pathinfo((string) parse_url($publicUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp4';
        }

        $metadata = array_merge((array) ($record?->metadata ?? []), (array) ($data['metadata'] ?? []), [
            'provider' => 'contabo_object_storage',
            'fetch_status' => 'completed',
            'fetch_mode' => $sourceMode,
            'storage_target' => 'contabo_object_storage',
            'last_message' => 'Video source stored on Contabo Object Storage.',
            'source_url' => $remoteUrl !== '' ? $remoteUrl : null,
            'object_key' => $objectKey,
            'bucket' => $service->bucket(),
            'endpoint' => $service->endpoint(),
            'public_url' => $publicUrl,
            'download_url' => $publicUrl,
            'mp4_url' => $publicUrl,
            'playback_type' => strtolower($resolvedFormat) === 'm3u8' ? 'hls' : 'mp4',
            'last_synced_at' => now()->toDateTimeString(),
        ]);

        $payload = [
            'type' => 'contabo_object_storage',
            'url' => $publicUrl,
            'file_path' => $publicUrl,
            'quality' => (string) ($data['quality'] ?? $record?->quality ?? 'auto'),
            'format' => strtolower($resolvedFormat),
            'file_size' => $fileSize ?: $record?->file_size,
            'duration_seconds' => $data['duration_seconds'] ?? $record?->duration_seconds,
            'is_primary' => (bool) ($data['is_primary'] ?? $record?->is_primary ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'metadata' => $metadata,
        ];

        if ($record) {
            $record->update($payload);

            return $this->syncPlaybackReadiness($record->fresh());
        }

        return $this->syncPlaybackReadiness($owner->videoSources()->create($payload));
    }

    private function queueContaboRemoteFetch(
        ?VideoSource $record,
        string $remoteUrl,
        array $data,
        string $assetType,
        ContaboObjectStorageService $service
    ): VideoSource {
        $owner = $this->getOwnerRecord();
        $quality = (string) ($data['quality'] ?? $record?->quality ?? 'auto');
        $format = (string) ($data['format'] ?? $record?->format ?? 'mp4');
        $metadata = array_merge((array) ($record?->metadata ?? []), (array) ($data['metadata'] ?? []), [
            'provider' => 'contabo_object_storage',
            'fetch_status' => 'queued',
            'fetch_mode' => 'queue',
            'storage_target' => 'contabo_object_storage',
            'last_message' => 'Contabo Object Storage fetch queued.',
            'source_url' => $remoteUrl,
            'bucket' => $service->bucket(),
            'endpoint' => $service->endpoint(),
            'queued_at' => now()->toDateTimeString(),
            'last_synced_at' => now()->toDateTimeString(),
        ]);

        $payload = [
            'type' => 'contabo_object_storage',
            'url' => $remoteUrl,
            'file_path' => null,
            'quality' => $quality !== '' ? $quality : 'auto',
            'format' => $format !== '' && $format !== 'auto' ? strtolower($format) : 'mp4',
            'file_size' => null,
            'duration_seconds' => $data['duration_seconds'] ?? $record?->duration_seconds,
            'is_primary' => (bool) ($data['is_primary'] ?? $record?->is_primary ?? false),
            'is_active' => false,
            'metadata' => $metadata,
        ];

        if ($record) {
            $record->update($payload);
            $videoSource = $record->fresh();
        } else {
            $videoSource = $owner->videoSources()->create($payload);
        }

        FetchVideoFromUrlJob::dispatch(
            $videoSource->id,
            $remoteUrl,
            $owner::class,
            (int) $owner->id,
            $quality,
            $format,
            'contabo_object_storage'
        )->onQueue('contabo-imports');

        return $videoSource;
    }

    private function normalizeContaboUploadState(mixed $state): string
    {
        if (is_array($state)) {
            $state = reset($state) ?: '';
        }

        return trim((string) $state);
    }

    private function isRemoteVideoPath(string $path): bool
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    private function contaboObjectExists(string $path, ContaboObjectStorageService $service): bool
    {
        try {
            return Storage::disk($service->diskName())->exists($service->normalizeKey($path));
        } catch (\Throwable) {
            return false;
        }
    }

    private function contaboObjectSize(string $key, ContaboObjectStorageService $service): ?int
    {
        try {
            return Storage::disk($service->diskName())->size($key);
        } catch (\Throwable) {
            return null;
        }
    }
}
