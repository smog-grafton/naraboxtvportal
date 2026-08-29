<?php

namespace App\Services;

use App\Models\VideoSource;
use App\Support\LegacyCdnUrlResolver;
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
    public function toCandidate(VideoSource $source, bool $primary = false, ?string $resolvedUrl = null): array
    {
        $this->hydrateIdentity($source);
        $url = (string) app(LegacyCdnUrlResolver::class)->resolve($resolvedUrl ?: $this->sourceUrl($source));
        $healthStatus = $this->healthStatusForUrl($source, $url);
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $isHls = str_ends_with($path, '.m3u8');
        $role = $isHls
            ? 'hls_master'
            : ($url === $this->sourceUrl($source) ? $source->media_role : 'playback_progressive');
        $quality = $role === 'hls_master'
            ? 'auto'
            : (string) ($source->quality_label ?: $source->quality ?: 'auto');
        $format = $isHls
            ? 'm3u8'
            : strtolower((string) (pathinfo($path, PATHINFO_EXTENSION) ?: $source->format ?: 'mp4'));
        $browserCompatible = $this->isBrowserCompatibleUrl($url, $format);

        return [
            'id' => $source->id,
            'role' => $role,
            'server' => $source->server_key,
            'server_key' => $source->server_key,
            'source_group' => $source->source_group,
            'format' => $format,
            'quality' => strtolower($quality),
            'is_primary' => $primary,
            'isPrimary' => $primary,
            'configured_primary' => (bool) $source->is_primary,
            'is_active' => (bool) $source->is_active,
            'playback_eligible' => (bool) $source->is_active,
            'browser_compatible' => $browserCompatible,
            'health_status' => $healthStatus,
            'verified_at' => $healthStatus === 'healthy'
                ? $source->verified_at?->toIso8601String()
                : null,
            'url' => $url,
            'type' => $source->type,
            'duration' => $source->duration_seconds,
        ];
    }

    /**
     * Return every concrete playback artifact recorded by one source row.
     *
     * Older fetched rows commonly retain the ingestion URL in `url` while the
     * successful local copy lives in `file_path`. The local artifact must be
     * tried first, without discarding CDN or original URLs as fallbacks.
     *
     * @return array<int, string>
     */
    public function candidateUrls(VideoSource $source): array
    {
        $metadata = (array) ($source->metadata ?? []);
        $isManagedImport = in_array($source->type, ['nbx-engine', 'tele_ob'], true)
            || ($metadata['provider'] ?? null) === 'nbx_engine'
            || isset($metadata['nbx_job_id'])
            || isset($metadata['cdn_asset_id']);
        $storedFileUrl = $this->storedFileUrl($source);
        $primaryUrl = $this->sourceUrl($source);
        $candidates = in_array($source->type, ['fetched', 'local'], true)
            ? [
                $storedFileUrl,
                $metadata['hls_master_url'] ?? null,
                $metadata['hls_url'] ?? null,
                $metadata['mp4_play_url'] ?? null,
                $metadata['mp4_url'] ?? null,
                $metadata['download_mp4_url'] ?? null,
                $metadata['public_url'] ?? null,
                $primaryUrl,
                $source->url,
                $metadata['original_url'] ?? null,
                $metadata['source_url'] ?? null,
            ]
            : [
                $primaryUrl,
                $metadata['hls_master_url'] ?? null,
                $metadata['hls_url'] ?? null,
                $metadata['mp4_play_url'] ?? null,
                $metadata['mp4_url'] ?? null,
                $metadata['download_mp4_url'] ?? null,
                $metadata['public_url'] ?? null,
                $storedFileUrl,
                $source->full_url,
                $source->url,
                $metadata['original_url'] ?? null,
                $metadata['source_url'] ?? null,
            ];

        return collect($candidates)
            ->filter(function (mixed $candidate) use ($isManagedImport): bool {
                if (! is_string($candidate) || trim($candidate) === '') {
                    return false;
                }

                $url = trim($candidate);
                if (preg_match('~^https?://~i', $url) !== 1) {
                    return false;
                }

                $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                if ($host === 't.me' || str_ends_with($host, '.t.me') || str_contains($host, 'telegram.')) {
                    return false;
                }

                return ! $isManagedImport || $this->isManagedPlaybackUrl($url);
            })
            ->map(fn (string $candidate): string => (string) app(LegacyCdnUrlResolver::class)->resolve(trim($candidate)))
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function toCandidates(VideoSource $source, bool $primary = false): array
    {
        return collect($this->candidateUrls($source))
            ->map(fn (string $url, int $index): array => $this->toCandidate(
                $source,
                $primary && $index === 0,
                $url,
            ))
            ->all();
    }

    public function sourceUrl(VideoSource $source): ?string
    {
        $metadata = (array) ($source->metadata ?? []);
        $role = $source->media_role ?: ($metadata['source_role'] ?? null);
        $isManagedImport = in_array($source->type, ['nbx-engine', 'tele_ob'], true)
            || ($metadata['provider'] ?? null) === 'nbx_engine'
            || isset($metadata['nbx_job_id'])
            || isset($metadata['cdn_asset_id']);
        $storedFileUrl = $this->storedFileUrl($source);

        // Legacy Tele-OB rows often keep the Telegram message in `url` while
        // the fetched Contabo/NBX output lives in metadata. The fetched output
        // is the playable contract; the import URL is provenance only.
        $roleCandidates = match ($role) {
            'hls_master' => [
                $metadata['hls_master_url'] ?? null,
                $metadata['hls_url'] ?? null,
                $storedFileUrl,
                $source->url,
            ],
            'faststart_mp4', 'playback_progressive' => [
                $storedFileUrl,
                $metadata['mp4_play_url'] ?? null,
                $metadata['mp4_url'] ?? null,
                $metadata['download_mp4_url'] ?? null,
                $source->full_url,
                $source->url,
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
                    $storedFileUrl,
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

            return app(LegacyCdnUrlResolver::class)->resolve($candidate);
        }

        return null;
    }

    private function storedFileUrl(VideoSource $source): ?string
    {
        $filePath = is_string($source->file_path) ? trim($source->file_path) : '';
        if ($filePath === '') {
            return null;
        }
        if (preg_match('~^https?://~i', $filePath) === 1) {
            return $filePath;
        }

        return asset('storage/'.ltrim($filePath, '/'));
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

        return true;
    }

    private function score(VideoSource $source, bool $browser): int
    {
        $score = match ($source->media_role) {
            'hls_master' => 0,
            'faststart_mp4' => 20,
            'playback_progressive' => 30,
            'source_original' => 60,
            default => 70,
        };

        if ($source->health_status === 'healthy') {
            $score -= 2;
        } elseif ($source->health_status === 'degraded') {
            $score += 3;
        } elseif ($source->health_status === 'unknown') {
            $score += 1;
        } elseif (in_array($source->health_status, ['unreachable', 'invalid'], true)) {
            $score += 5;
        }
        if ($source->is_primary) {
            $score -= 1;
        }
        if ($browser && $this->isBrowserCompatibleUrl($this->sourceUrl($source), (string) $source->format)) {
            $score -= 1;
        } elseif ($browser) {
            // Keep a valid original/container URL as a last-resort candidate.
            // Availability and browser decode support are different facts.
            $score += 20;
        }

        return $score;
    }

    private function healthStatusForUrl(VideoSource $source, string $url): string
    {
        $status = (string) ($source->health_status ?: 'unknown');
        if (! in_array($status, ['unreachable', 'invalid'], true)) {
            return $status;
        }

        // Health belongs to the URL that was actually probed, and even a
        // matching old failure is only advisory for playback. Let clients try
        // concrete media URLs and report only player-observed failures.
        return 'unknown';
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

        return preg_match('/\.(m3u8|mp4|m4v|webm|mkv|mov|avi)(?:$|[?#])/i', $url) === 1
            || str_contains($path, '/faststart/')
            || str_contains($path, '/hls/');
    }

    private function isBrowserCompatibleUrl(?string $url, string $format = ''): bool
    {
        $path = strtolower((string) parse_url((string) $url, PHP_URL_PATH));
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['mkv', 'mk3d', 'mka', 'mov', 'avi'], true)) {
            return false;
        }
        if (in_array($extension, ['m3u8', 'mp4', 'm4v', 'webm'], true)) {
            return true;
        }

        $normalizedFormat = strtolower(trim($format));
        if (in_array($normalizedFormat, ['hls', 'm3u8', 'mp4', 'm4v', 'webm'], true)) {
            return true;
        }

        return $normalizedFormat === '';
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
