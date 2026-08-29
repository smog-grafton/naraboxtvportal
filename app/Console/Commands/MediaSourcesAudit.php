<?php

namespace App\Console\Commands;

use App\Models\VideoSource;
use App\Services\ContaboObjectStorageService;
use App\Services\MediaSourceSelectionService;
use App\Services\NbxVideoSourceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MediaSourcesAudit extends Command
{
    protected $signature = 'media:sources-audit
        {--dry-run : Explicitly make no repairs (this is also the default)}
        {--repair : Apply safe, non-destructive metadata and primary-selection repairs}
        {--limit=500 : Maximum source records to inspect}';

    protected $description = 'Audit video-source health, primary selection, storage references, and schema readiness';

    public function handle(
        MediaSourceSelectionService $selection,
        ContaboObjectStorageService $contabo,
        NbxVideoSourceService $nbx,
    ): int {
        $repair = (bool) $this->option('repair');
        if ($repair && (bool) $this->option('dry-run')) {
            $this->error('Choose either --dry-run or --repair, not both.');

            return self::INVALID;
        }

        $problems = [];
        $required = [
            'media_role',
            'server_key',
            'health_status',
            'last_health_check_at',
            'storage_object_key',
            'nbx_asset_id',
            'processing_job_id',
        ];
        foreach ($required as $column) {
            if (! Schema::hasColumn('video_sources', $column)) {
                $problems[] = ['schema', 'video_sources.'.$column, 'Run the reliability migration', 'high', '-'];
            }
        }
        if ($problems !== []) {
            $this->table(['Problem', 'Record', 'Proposed repair', 'Risk', 'Storage'], $problems);

            return self::FAILURE;
        }

        $sources = VideoSource::query()
            ->with('sourceable')
            ->latest('id')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        foreach ($sources as $source) {
            if ($repair) {
                $selection->hydrateIdentity($source);
                $source->refresh();
            }
            $record = 'video_source#'.$source->id;
            $estimated = $source->file_size ? $this->formatBytes((int) $source->file_size) : '-';
            $sourceUrl = (string) ($source->url ?: $source->file_path ?: '');
            $derivedContaboKey = $sourceUrl !== '' && $contabo->isContaboPublicUrl($sourceUrl)
                ? $contabo->objectKeyFromPublicUrl($sourceUrl)
                : null;
            if (! $source->storage_object_key && $derivedContaboKey) {
                $problems[] = ['Contabo identity not backfilled', $record, 'Persist object key and register it with NBX', 'low', $estimated];
            }

            if ($source->deleted_from_storage_at && $source->is_active) {
                $problems[] = ['deleted source active', $record, 'Deactivate source', 'low', $estimated];
                if ($repair) {
                    $source->forceFill(['is_active' => false, 'is_primary' => false])->saveQuietly();
                }
            }

            if ($source->is_primary && in_array($source->health_status, ['unreachable', 'invalid'], true)) {
                $problems[] = ['unhealthy primary', $record, 'Promote best verified fallback', 'medium', $estimated];
                if ($repair && $source->sourceable) {
                    $fallback = $selection->candidatesFor($source->sourceable)
                        ->first(fn (VideoSource $candidate): bool => $candidate->id !== $source->id && $candidate->health_status === 'healthy'
                        );
                    if ($fallback) {
                        $selection->promoteIfHealthy($fallback, 'media_sources_audit');
                    }
                }
            }

            if ($source->media_role === 'hls_master' && $source->health_status === 'healthy' && ! $source->is_primary) {
                $problems[] = ['healthy HLS not preferred', $record, 'Promote verified HLS', 'low', $estimated];
                if ($repair) {
                    $selection->promoteIfHealthy($source, 'media_sources_audit');
                }
            }

            if ($source->storage_object_key && $source->storage_disk && $source->storage_disk === $contabo->diskName()) {
                try {
                    if (! Storage::disk($source->storage_disk)->exists($source->storage_object_key)) {
                        $problems[] = ['missing Contabo object', $record, 'Mark unreachable and deactivate after review', 'high', $estimated];
                    }
                } catch (\Throwable) {
                    $problems[] = ['storage check failed', $record, 'Check object-storage credentials/permissions', 'medium', $estimated];
                }
            }

            $sourceMetadata = (array) ($source->metadata ?? []);
            $storageReference = (array) ($sourceMetadata['nbx_storage_reference'] ?? []);
            if ($source->storage_disk === $contabo->diskName()
                && $source->storage_object_key
                && empty($storageReference['id'])
            ) {
                $problems[] = ['direct object-storage source unregistered', $record, 'Register metadata with NBX (no upload)', 'low', $estimated];
                if ($repair && (bool) config('services.nbx_engine.enabled', false)) {
                    try {
                        $nbx->registerDirectStorageSource($source);
                    } catch (\Throwable) {
                        // The issue remains in the report for operator follow-up.
                    }
                }
            }
        }

        $groups = $sources->groupBy(fn (VideoSource $source): string => $source->sourceable_type.':'.$source->sourceable_id);
        foreach ($groups as $owner => $group) {
            $primaryCount = $group->where('is_primary', true)->count();
            if ($primaryCount > 1) {
                $problems[] = ['multiple primary sources', $owner, 'Keep the best policy candidate primary', 'medium', '-'];
                if ($repair && ($sourceable = $group->first()?->sourceable)) {
                    $preferred = $selection->preferredFor($sourceable);
                    if ($preferred) {
                        // This only normalizes the primary flags. It does not
                        // deactivate a fallback, delete an object, or enqueue
                        // another optimization job. Requiring a fresh health
                        // check here would leave legacy duplicate flags stuck
                        // forever even though candidate selection already has
                        // a deterministic best playable source.
                        $selection->promote($preferred, 'media_sources_audit_primary_dedupe', false);
                    }
                }
            }
        }

        if ($problems === []) {
            $this->info('No source-integrity problems were found in the inspected records.');

            return self::SUCCESS;
        }

        $this->table(['Problem', 'Record', 'Proposed repair', 'Risk', 'Storage'], $problems);
        $this->line($repair
            ? 'Safe metadata repairs were applied; no media objects were copied or deleted.'
            : 'Dry run only; no records or objects were changed.');

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 ** 2) {
            return number_format($bytes / 1024, 1).' KB';
        }
        if ($bytes < 1024 ** 3) {
            return number_format($bytes / (1024 ** 2), 1).' MB';
        }

        return number_format($bytes / (1024 ** 3), 2).' GB';
    }
}
