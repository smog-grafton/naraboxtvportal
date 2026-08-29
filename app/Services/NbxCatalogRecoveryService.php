<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\VideoSource;
use Illuminate\Database\Eloquent\Builder;

/**
 * Two-tier recovery for Portal's video_sources after NBX's database was
 * lost and rebuilt from Contabo storage (see NBX's
 * nbx:rebuild-catalog-from-storage). Kept separate from
 * NbxVideoSourceService (which already does the heavy lifting for both
 * tiers) so the "bulk disaster recovery" entry points don't grow that
 * already-large service further.
 */
class NbxCatalogRecoveryService
{
    public function __construct(
        private readonly NbxVideoSourceService $nbx,
    ) {}

    /**
     * Tier 1 — cheap. Re-runs NBX's own existing discovery lookup, which
     * already tries (in order) cdn_source_id, job_id, asset_id, video_ref,
     * then source_url. Because NBX's rebuild command sets external_job_id
     * on every row it recreates, a source whose stored processing_job_id /
     * metadata->nbx_job_id matches a recovered job folder will be found
     * here with no re-fetch or re-transcode.
     *
     * @return array{matched:bool,source:VideoSource}
     */
    public function rediscover(VideoSource $source): array
    {
        $result = $this->nbx->sync($source);
        $matched = (string) data_get($result->metadata, 'nbx_sync_status') === 'synced';

        return ['matched' => $matched, 'source' => $result];
    }

    /**
     * Tier 2 — expensive. Only for sources tier 1 could not match (their
     * final storage objects are truly gone, not just their database row).
     * Replays the original submission using the exact processing options
     * that were used before, via NbxVideoSourceService::hydrateProcessingForm()
     * — the same reconstruction the Filament edit form already relies on —
     * so this is a faithful resubmission, not a best-effort guess with
     * today's defaults. NBX's own idempotency check will find nothing
     * against its empty/rebuilt database and will fetch + transcode fresh.
     */
    public function resubmitForRecreation(VideoSource $source): VideoSource
    {
        $sourceable = $source->sourceable;
        if (! $sourceable) {
            throw new \RuntimeException("Video source {$source->id} has no sourceable (movie/episode) to resubmit against.");
        }

        $data = $this->nbx->hydrateProcessingForm($source);
        $assetType = $sourceable instanceof Episode ? 'episode' : 'movie';

        return $source->type === 'tele_ob'
            ? $this->nbx->submitTelegram($sourceable, $data, $assetType)
            : $this->nbx->submitRemote($sourceable, $data, $assetType);
    }

    /**
     * Video sources that show any evidence of prior NBX involvement.
     * Deliberately broader than the "nbx-engine"/"tele_ob" type filter
     * SyncNbxVideoSources uses — a legacy row with type "fetched" and a
     * populated nbx_asset_id would otherwise be silently skipped.
     *
     * Shared by the nbx:resync-catalog command and the Filament recovery
     * page so eligibility can't drift between the two.
     */
    public function eligibleForRediscoveryQuery(): Builder
    {
        return VideoSource::query()
            ->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNotNull('nbx_asset_id')
                    ->orWhereNotNull('processing_job_id')
                    ->orWhere('metadata->nbx_job_id', '!=', null)
                    ->orWhere('metadata->cdn_asset_id', '!=', null);
            });
    }

    /**
     * Subset of the above that tier 1 already tried and could not match —
     * the only sources tier 2 (re-fetch/re-transcode) should ever target.
     */
    public function eligibleForRecreationQuery(): Builder
    {
        return $this->eligibleForRediscoveryQuery()
            ->where('metadata->nbx_sync_status', 'failed');
    }
}
