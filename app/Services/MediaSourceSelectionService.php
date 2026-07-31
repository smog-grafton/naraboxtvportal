<?php

namespace App\Services;

use App\Models\VideoSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MediaSourceSelectionService
{
    /**
     * @return Collection<int, VideoSource>
     */
    public function candidatesFor(Model $sourceable, ?string $platform = null): Collection
    {
        $limit = max(1, min(5, (int) config('video_sources.health.candidate_limit', 5)));
        $browser = in_array(strtolower((string) $platform), ['web', 'ios'], true);

        return $sourceable->videoSources()
            ->where('is_active', true)
            ->whereNull('deleted_from_storage_at')
            ->get()
            ->each(fn (VideoSource $source) => $this->hydrateIdentity($source))
            ->filter(fn (VideoSource $source): bool => $this->isCandidate($source, $browser))
            ->sortBy(fn (VideoSource $source): int => $this->score($source, $browser))
            ->unique(fn (VideoSource $source): string => (string) $this->sourceUrl($source))
            ->take($limit)
            ->values();
    }

    public function preferredFor(Model $sourceable, ?string $platform = null): ?VideoSource
    {
        return $this->candidatesFor($sourceable, $platform)->first();
    }

    public function promoteIfHealthy(VideoSource $source, string $reason = 'verified_source_policy'): bool
    {
        return $this->promote($source, $reason, true);
    }

    public function promote(
        VideoSource $source,
        string $reason = 'source_policy',
        bool $requireHealthy = true,
    ): bool {
        $source->refresh();
        if (! $source->is_active
            || ($requireHealthy && ($source->health_status !== 'healthy' || ! $source->verified_at))
        ) {
            return false;
        }

        DB::transaction(function () use ($source, $reason): void {
            VideoSource::withoutEvents(function () use ($source, $reason): void {
                VideoSource::query()
                    ->where('sourceable_type', $source->sourceable_type)
                    ->where('sourceable_id', $source->sourceable_id)
                    ->whereKeyNot($source->id)
                    ->update(['is_primary' => false]);

                $metadata = array_merge((array) $source->metadata, [
                    'primary_selection_reason' => $reason,
                    'primary_selected_at' => now()->toIso8601String(),
                ]);

                $source->forceFill([
                    'is_primary' => true,
                    'primary_changed_at' => now(),
                    'metadata' => $metadata,
                ])->save();
            });
        });

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toCandidate(VideoSource $source, bool $primary = false): array
    {
        $this->hydrateIdentity($source);
        $url = (string) $this->sourceUrl($source);
        $healthStatus = $this->healthStatusForUrl($source, $url);
        $quality = $source->media_role === 'hls_master'
            ? 'auto'
            : (string) ($source->quality_label ?: $source->quality ?: 'auto');

        return [
            'id' => $source->id,
            'role' => $source->media_role,
            'server' => $source->server_key,
            'server_key' => $source->server_key,
            'source_group' => $source->source_group,
            'format' => strtolower((string) ($source->format ?: 'mp4')),
            'quality' => strtolower($quality),
            'is_primary' => $primary,
            'isPrimary' => $primary,
            'configured_primary' => (bool) $source->is_primary,
            'is_active' => (bool) $source->is_active,
            'health_status' => $healthStatus,
            'verified_at' => $healthStatus === 'healthy'
                ? $source->verified_at?->toIso8601String()
                : null,
            'url' => $url,
            'type' => $source->type,
            'duration' => $source->duration_seconds,
        ];
    }

    public function sourceUrl(VideoSource $source): ?string
    {
        $metadata = (array) ($source->metadata ?? []);
        $role = $source->media_role ?: ($metadata['source_role'] ?? null);
        $isManagedImport = in_array($source->type, ['nbx-engine', 'tele_ob'], true)
            || ($metadata['provider'] ?? null) === 'nbx_engine'
            || isset($metadata['nbx_job_id'])
            || isset($metadata['cdn_asset_id']);

        // Legacy Tele-OB rows often keep the Telegram message in `url` while
        // the fetched Contabo/NBX output lives in metadata. The fetched output
        // is the playable contract; the import URL is provenance only.
        $roleCandidates = match ($role) {
            'hls_master' => [
                $metadata['hls_master_url'] ?? null,
                $metadata['hls_url'] ?? null,
                $source->url,
                $source->file_path,
            ],
            'faststart_mp4', 'playback_progressive' => [
                $metadata['mp4_play_url'] ?? null,
                $metadata['mp4_url'] ?? null,
                $metadata['download_mp4_url'] ?? null,
                $source->url,
                $source->file_path,
            ],
            default => $isManagedImport
                ? [
                    $metadata['hls_master_url'] ?? null,
                    $metadata['hls_url'] ?? null,
                    $metadata['mp4_play_url'] ?? null,
                    $metadata['mp4_url'] ?? null,
                    $metadata['download_mp4_url'] ?? null,
                    $source->url,
                    $source->file_path,
                    $metadata['original_url'] ?? null,
                ]
                : [
                    $source->full_url,
                    $source->url,
                    $source->file_path,
                    $metadata['public_url'] ?? null,
                    $metadata['original_url'] ?? null,
                ],
        };

        foreach ($roleCandidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $candidate = trim($candidate);
            if ($isManagedImport && ! $this->isManagedPlaybackUrl($candidate)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    public function hydrateIdentity(VideoSource $source): void
    {
        $metadata = (array) ($source->metadata ?? []);
        $url = (string) ($this->preferredManagedMetadataUrl($source, $metadata)
            ?: $source->url
            ?: $source->file_path
            ?: '');
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $isHls = in_array(strtolower((string) $source->format), ['hls', 'm3u8'], true)
            || str_ends_with($path, '.m3u8');
        $contabo = app(ContaboObjectStorageService::class);
        $contaboObjectKey = $url !== '' && $contabo->isContaboPublicUrl($url)
            ? $contabo->objectKeyFromPublicUrl($url)
            : null;
        $role = $source->media_role
            ?: ($metadata['source_role'] ?? null)
            ?: ($isHls
                ? 'hls_master'
                : (str_contains($path, '/faststart/') || str_contains($path, '_play.mp4')
                    ? 'faststart_mp4'
                    : 'playback_progressive'));
        $server = $source->server_key
            ?: ($metadata['provider'] ?? null)
            ?: match ($source->type) {
                'nbx-engine', 'tele_ob' => 'nbx',
                'contabo_object_storage' => 'contabo',
                'bunny_stream' => 'bunny',
                default => 'legacy',
            };
        $group = $source->source_group
            ?: ($metadata['nbx_job_id'] ?? null)
            ?: ($metadata['cdn_asset_id'] ?? null)
            ?: "{$server}:{$source->sourceable_type}:{$source->sourceable_id}";

        $changes = array_filter([
            'media_role' => $role,
            'server_key' => $server,
            'source_group' => (string) $group,
            'quality_label' => $isHls ? 'auto' : ($source->quality ?: 'auto'),
            'storage_disk' => $source->storage_disk ?: ($metadata['object_disk'] ?? null) ?: ($contaboObjectKey ? $contabo->diskName() : null),
            'storage_bucket' => $source->storage_bucket ?: ($metadata['bucket'] ?? null) ?: ($contaboObjectKey ? $contabo->bucket() : null),
            'storage_object_key' => $source->storage_object_key ?: ($metadata['object_key'] ?? null) ?: $contaboObjectKey,
            'nbx_asset_id' => $source->nbx_asset_id ?: ($metadata['cdn_asset_id'] ?? null),
            'processing_job_id' => $source->processing_job_id ?: ($metadata['nbx_job_id'] ?? null),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($source->isDirty(array_keys($changes)) || collect($changes)->contains(fn ($value, $key) => $source->getAttribute($key) !== $value)) {
            VideoSource::withoutEvents(fn () => $source->forceFill($changes)->save());
        }
    }

    private function isCandidate(VideoSource $source, bool $browser): bool
    {
        $url = $this->sourceUrl($source);
        if (! $url) {
            return false;
        }

        if (in_array($source->health_status, ['unreachable', 'invalid'], true)) {
            $metadata = (array) ($source->metadata ?? []);
            $checkedUrl = is_string($metadata['health_checked_url'] ?? null)
                ? trim($metadata['health_checked_url'])
                : null;
            $ttlMinutes = max(1, (int) config('video_sources.health.ttl_minutes', 30));
            $isFresh = $source->last_health_check_at?->gte(now()->subMinutes($ttlMinutes)) ?? false;

            // A legacy Tele-OB row may have been marked unreachable when its
            // `url` still pointed at Telegram. Do not let that stale verdict
            // suppress a different fetched Contabo/NBX artifact now selected
            // from metadata.
            if ($isFresh && $checkedUrl !== null && hash_equals($checkedUrl, $url)) {
                return false;
            }
        }

        if ($browser && in_array(strtolower((string) $source->format), ['mkv', 'mov', 'avi', 'webm'], true)) {
            return false;
        }

        return true;
    }

    private function score(VideoSource $source, bool $browser): int
    {
        $score = match ($source->media_role) {
            'hls_master' => 10,
            'faststart_mp4' => 20,
            'playback_progressive' => 30,
            'source_original' => 60,
            default => 70,
        };

        if ($source->health_status === 'healthy') {
            $score -= 4;
        } elseif ($source->health_status === 'degraded') {
            $score += 20;
        } elseif ($source->health_status === 'unknown') {
            $score += 8;
        } elseif (in_array($source->health_status, ['unreachable', 'invalid'], true)) {
            // Stale or URL-mismatched failures remain a last-resort candidate
            // until the selected artifact is checked directly.
            $score += 80;
        }
        if ($source->is_primary) {
            $score -= 1;
        }
        if ($browser && in_array(strtolower((string) $source->format), ['mp4', 'm3u8', 'hls'], true)) {
            $score -= 1;
        }

        return $score;
    }

    private function healthStatusForUrl(VideoSource $source, string $url): string
    {
        $status = (string) ($source->health_status ?: 'unknown');
        if (! in_array($status, ['unreachable', 'invalid'], true)) {
            return $status;
        }

        $metadata = (array) ($source->metadata ?? []);
        $checkedUrl = is_string($metadata['health_checked_url'] ?? null)
            ? trim($metadata['health_checked_url'])
            : null;

        // Health belongs to the URL that was actually probed. If source
        // resolution has since replaced a Telegram/import URL with a fetched
        // artifact, do not serialize the old failure as the artifact's state.
        if ($checkedUrl !== null && ! hash_equals($checkedUrl, $url)) {
            return 'unknown';
        }

        return $status;
    }

    /**
     * The portal must never hand a Telegram post or an NBX control-plane URL
     * to a video element. Managed imports are playable only through a concrete
     * HLS or progressive media artifact.
     */
    private function isManagedPlaybackUrl(string $url): bool
    {
        if (preg_match('~^https?://~i', $url) !== 1) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === 't.me' || str_ends_with($host, '.t.me') || str_contains($host, 'telegram.')) {
            return false;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return preg_match('/\.(m3u8|mp4|m4v)(?:$|[?#])/i', $url) === 1
            || str_contains($path, '/faststart/')
            || str_contains($path, '/hls/');
    }

    private function preferredManagedMetadataUrl(VideoSource $source, array $metadata): ?string
    {
        $isManagedImport = in_array($source->type, ['nbx-engine', 'tele_ob'], true)
            || ($metadata['provider'] ?? null) === 'nbx_engine';
        if (! $isManagedImport) {
            return null;
        }

        $role = (string) ($source->media_role ?: ($metadata['source_role'] ?? ''));
        $format = strtolower((string) $source->format);
        $isHlsRow = $role === 'hls_master' || in_array($format, ['hls', 'm3u8'], true);
        $candidates = $isHlsRow
            ? [
                $metadata['hls_master_url'] ?? null,
                $metadata['hls_url'] ?? null,
            ]
            : [
                $metadata['mp4_play_url'] ?? null,
                $metadata['mp4_url'] ?? null,
                $metadata['download_mp4_url'] ?? null,
                $metadata['hls_master_url'] ?? null,
                $metadata['hls_url'] ?? null,
            ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $this->isManagedPlaybackUrl(trim($candidate))) {
                return trim($candidate);
            }
        }

        return null;
    }
}
