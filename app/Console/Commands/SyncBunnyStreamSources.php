<?php

namespace App\Console\Commands;

use App\Models\VideoSource;
use App\Services\BunnyStreamClientService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SyncBunnyStreamSources extends Command
{
    protected $signature = 'bunny:sync-stream-sources
        {--limit=100 : Maximum Bunny Stream sources to refresh}
        {--force : Refresh all Bunny Stream sources, including recently synced records}';

    protected $description = 'Refresh Bunny Stream video metadata and sync download URLs for video sources';

    public function handle(BunnyStreamClientService $bunny): int
    {
        if (! $bunny->isConfigured()) {
            $this->error('Bunny Stream is not configured.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');

        $sources = VideoSource::query()
            ->where(function (Builder $query): void {
                $query->where('type', 'bunny_stream')
                    ->orWhere('metadata->provider', 'bunny_stream')
                    ->orWhereNotNull('metadata->bunny_stream_video_id');
            })
            ->when(! $force, function (Builder $query): void {
                $query->where(function (Builder $stale): void {
                    $stale->whereNull('metadata->last_synced_at')
                        ->orWhere('metadata->last_synced_at', '<=', now()->subMinutes(5)->toDateTimeString())
                        ->orWhereNull('duration_seconds')
                        ->orWhereNull('file_size')
                        ->orWhereIn('metadata->bunny_stream_status', [0, 1, 2, 6, 7, 9, 10]);
                });
            })
            ->latest('id')
            ->limit($limit)
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($sources as $source) {
            $metadata = is_array($source->metadata) ? $source->metadata : [];
            $videoId = (string) ($metadata['bunny_stream_video_id'] ?? '');
            if ($videoId === '') {
                $skipped++;
                continue;
            }

            $response = $bunny->getVideo($videoId);
            if (! ($response['ok'] ?? false) || ! is_array($response['data'] ?? null)) {
                $metadata['last_sync_error'] = (string) ($response['error'] ?? 'Unable to fetch Bunny Stream video.');
                $metadata['last_synced_at'] = now()->toDateTimeString();
                $source->forceFill(['metadata' => $metadata])->save();
                $skipped++;
                continue;
            }

            $video = (array) $response['data'];
            $playback = $bunny->buildPlaybackPayload($videoId, $video);
            $hlsUrl = (string) ($playback['hls_master_url'] ?? '');
            $downloadUrl = (string) ($playback['download_url'] ?? '');
            $mp4Url = (string) (($playback['mp4_play_url'] ?? null) ?: ($playback['mp4_url'] ?? ''));
            $originalUrl = (string) ($playback['original_url'] ?? '');
            $playbackUrl = $hlsUrl !== '' ? $hlsUrl : ($mp4Url !== '' ? $mp4Url : $source->url);
            $storageSize = isset($video['storageSize']) ? (int) $video['storageSize'] : null;
            $duration = isset($video['length']) ? (int) $video['length'] : null;

            $metadata = array_merge($metadata, [
                'provider' => 'bunny_stream',
                'fetch_status' => 'completed',
                'last_message' => 'Bunny Stream metadata refreshed.',
                'bunny_stream_video_id' => $videoId,
                'bunny_stream_status' => $playback['status'] ?? ($video['status'] ?? null),
                'bunny_stream_status_label' => $playback['status_label'] ?? null,
                'bunny_stream_encode_progress' => $playback['encode_progress'] ?? ($video['encodeProgress'] ?? null),
                'bunny_stream_playback' => $playback,
                'bunny_stream_video' => $video,
                'hls_master_url' => $hlsUrl !== '' ? $hlsUrl : null,
                'original_url' => $originalUrl !== '' ? $originalUrl : null,
                'mp4_play_url' => $mp4Url !== '' ? $mp4Url : null,
                'mp4_url' => $mp4Url !== '' ? $mp4Url : null,
                'download_url' => $downloadUrl !== '' ? $downloadUrl : null,
                'last_sync_error' => null,
                'last_synced_at' => now()->toDateTimeString(),
            ]);

            $source->forceFill([
                'url' => $playbackUrl,
                'file_path' => $playbackUrl,
                'format' => $hlsUrl !== '' ? 'm3u8' : ($source->format ?: 'mp4'),
                'file_size' => $storageSize && $storageSize > 0 ? $storageSize : $source->file_size,
                'duration_seconds' => $duration && $duration > 0 ? $duration : $source->duration_seconds,
                'is_active' => $playbackUrl !== null && $playbackUrl !== '',
                'metadata' => $metadata,
            ])->save();

            $source->refresh()->syncToDownloadSource();
            $updated++;
        }

        $this->info("Refreshed {$updated} Bunny Stream source(s); skipped {$skipped}.");

        return self::SUCCESS;
    }
}
