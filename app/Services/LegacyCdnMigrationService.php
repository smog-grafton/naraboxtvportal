<?php

namespace App\Services;

use App\Models\VideoSource;
use App\Support\LegacyCdnUrlResolver;
use Illuminate\Database\Eloquent\Model;

class LegacyCdnMigrationService
{
    public function __construct(
        private readonly NbxVideoSourceService $nbxVideoSource,
        private readonly LegacyCdnUrlResolver $resolver,
    ) {}

    public function canMigrate(VideoSource $source): bool
    {
        return $this->resolver->isLegacyMediaUrl($this->sourceUrl($source));
    }

    /**
     * Start one supervised legacy-CDN migration. The NBX job receives both
     * candidates and owns the actual media validation/fallback work.
     *
     * @return array{status:string,source:?VideoSource,message:string}
     */
    public function start(VideoSource $source, string $assetType): array
    {
        $legacyUrl = $this->sourceUrl($source);
        if (! $this->resolver->isLegacyMediaUrl($legacyUrl)) {
            throw new \RuntimeException('This source is not a recognized legacy CDN URL.');
        }

        $resolvedUrl = $this->resolver->resolve($legacyUrl);
        if (! is_string($resolvedUrl) || $resolvedUrl === '') {
            throw new \RuntimeException('The legacy CDN URL could not be normalized.');
        }

        $idempotencyKey = hash('sha256', implode('|', [
            'portal-legacy-cdn-migration-v1',
            $source->sourceable_type,
            (string) $source->sourceable_id,
            (string) $source->id,
            $resolvedUrl,
        ]));

        $existing = $this->findExistingMigration($source->sourceable, $idempotencyKey);
        if ($existing) {
            $existingMigration = (array) data_get($existing->metadata, 'legacy_cdn_migration', []);
            $state = (string) ($existingMigration['state'] ?? 'queued');

            if ($state === 'migrated' || (($existing->metadata['fetch_status'] ?? null) === 'completed')) {
                return [
                    'status' => 'already_migrated',
                    'source' => $existing,
                    'message' => 'This legacy source has already been migrated to NBX.',
                ];
            }

            if ($this->isActiveState($state) && filled($existingMigration['nbx_job_id'] ?? null)) {
                return [
                    'status' => 'already_running',
                    'source' => $existing,
                    'message' => 'A migration is already in progress for this source.',
                ];
            }
        }

        $migration = [
            'kind' => 'legacy_cdn',
            'state' => 'trying_legacy_public',
            'source_id' => $source->id,
            'legacy_asset_id' => null,
            'legacy_source_id' => null,
            'lookup_url' => $legacyUrl,
            'resolved_source_url' => $resolvedUrl,
            'original_source_url' => null,
            'fallback_source_url' => $resolvedUrl,
            'stored_filename' => basename((string) parse_url($resolvedUrl, PHP_URL_PATH)) ?: null,
            'idempotency_key' => $idempotencyKey,
            'requested_at' => now()->toIso8601String(),
        ];

        /** @var Model $sourceable */
        $nbxSource = $this->nbxVideoSource->submitRemote($source->sourceable, [
            'url' => $resolvedUrl,
            'idempotency_key' => $idempotencyKey,
            'import_mode' => 'queue',
            'nbx_storage_target' => 'auto',
            'nbx_faststart' => true,
            'nbx_compress_enabled' => false,
            'nbx_hls_480p' => true,
            'nbx_hls_720p' => false,
            'nbx_hls_1080p' => false,
            'nbx_allow_downloads' => true,
            'nbx_allow_hls_streaming' => true,
            'nbx_retention_policy' => 'optimized_only',
            'is_primary' => (bool) $source->is_primary,
            'is_active' => (bool) $source->is_active,
            'migration' => $migration,
            'metadata' => [
                'legacy_cdn_migration' => $migration,
            ],
        ], $assetType);

        $sourceMetadata = (array) ($source->metadata ?? []);
        $sourceMetadata['legacy_cdn_migration'] = array_merge($migration, [
            'state' => 'queued',
            'nbx_job_id' => data_get($nbxSource->metadata, 'nbx_job_id'),
            'nbx_source_id' => $nbxSource->id,
            'queued_at' => now()->toIso8601String(),
        ]);
        $source->update(['metadata' => $sourceMetadata]);

        return [
            'status' => 'started',
            'source' => $nbxSource,
            'message' => 'Migration started from the direct legacy CDN storage path. NBX will validate the media before processing it.',
        ];
    }

    private function findExistingMigration(Model $sourceable, string $idempotencyKey): ?VideoSource
    {
        return $sourceable->videoSources()
            ->whereIn('type', ['nbx-engine', 'tele_ob'])
            ->get()
            ->first(function (VideoSource $candidate) use ($idempotencyKey): bool {
                $migration = (array) data_get($candidate->metadata, 'legacy_cdn_migration', []);

                return (string) ($migration['idempotency_key'] ?? '') === $idempotencyKey;
            });
    }

    private function sourceUrl(VideoSource $source): string
    {
        $metadata = (array) ($source->metadata ?? []);

        foreach ([
            $source->url,
            $source->file_path,
            $metadata['public_url'] ?? null,
            $metadata['source_url'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private function isActiveState(string $state): bool
    {
        return in_array($state, [
            'queued',
            'trying_original',
            'trying_legacy_public',
            'legacy_cdn_security_challenge',
            'legacy_push_requested',
            'receiving_legacy_push',
            'processing',
        ], true);
    }
}
