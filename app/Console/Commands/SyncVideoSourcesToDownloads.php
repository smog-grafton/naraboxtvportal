<?php

namespace App\Console\Commands;

use App\Models\VideoSource;
use App\Models\DownloadSource;
use Illuminate\Console\Command;

class SyncVideoSourcesToDownloads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'videos:sync-downloads {--movie-id= : Sync for specific movie ID} {--episode-id= : Sync for specific episode ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync existing video sources to download sources';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $movieId = $this->option('movie-id');
        $episodeId = $this->option('episode-id');

        $query = VideoSource::whereIn('type', [
            'url',
            'direct',
            'upload',
            'uploaded',
            'local',
            'fetched',
            'curl',
            'cdn',
            'legacy_cdn',
            'contabo',
            'contabo_object_storage',
            'tele_ob',
            'bunny_stream',
            'nbx-engine',
        ])
            ->where('is_active', true);

        if ($movieId) {
            $query->where('sourceable_type', 'App\Models\Movie')
                  ->where('sourceable_id', $movieId);
        } elseif ($episodeId) {
            $query->where('sourceable_type', 'App\Models\Episode')
                  ->where('sourceable_id', $episodeId);
        }

        $videoSources = $query->get();
        $synced = 0;
        $skipped = 0;

        foreach ($videoSources as $videoSource) {
            $downloadUrl = $this->downloadUrlFor($videoSource);
            $downloadPathFormat = pathinfo((string) parse_url((string) $downloadUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            $downloadFormat = strtolower((string) ($downloadPathFormat ?: ($videoSource->format ?: 'mp4')));
            if (! $downloadUrl || $downloadFormat === 'm3u8' || str_ends_with(strtolower((string) parse_url($downloadUrl, PHP_URL_PATH)), '.m3u8')) {
                $skipped++;
                continue;
            }

            // Check if download source already exists
            $existing = DownloadSource::where('downloadable_type', $videoSource->sourceable_type)
                ->where('downloadable_id', $videoSource->sourceable_id)
                ->where('type', $videoSource->type)
                ->where(function($q) use ($videoSource) {
                    $downloadUrl = $this->downloadUrlFor($videoSource);
                    if ($this->usesDownloadUrlColumn($videoSource) && $downloadUrl) {
                        $q->where('url', $downloadUrl);
                    } elseif (in_array($videoSource->type, ['fetched', 'local']) && $videoSource->file_path) {
                        $q->where('file_path', $videoSource->file_path);
                    }
                })
                ->first();

            if ($existing) {
                $skipped++;
                continue;
            }

            // Create download source
            DownloadSource::create([
                'downloadable_type' => $videoSource->sourceable_type,
                'downloadable_id' => $videoSource->sourceable_id,
                'type' => $videoSource->type,
                'url' => $this->usesDownloadUrlColumn($videoSource) || ! $videoSource->file_path ? $downloadUrl : null,
                'file_path' => in_array($videoSource->type, ['fetched', 'local'], true) && $videoSource->file_path ? $videoSource->file_path : null,
                'quality' => $videoSource->quality ?: 'auto',
                'format' => $downloadFormat ?: 'mp4',
                'file_size' => $videoSource->file_size,
                'label' => ($videoSource->quality ?: 'auto') . ' ' . strtoupper($downloadFormat ?: 'mp4'),
                'is_active' => $videoSource->is_active,
            ]);

            $synced++;
        }

        $this->info("Synced {$synced} video source(s) to download sources.");
        if ($skipped > 0) {
            $this->info("Skipped {$skipped} video source(s) (already have download sources).");
        }

        return Command::SUCCESS;
    }

    private function downloadUrlFor(VideoSource $videoSource): ?string
    {
        $metadata = is_array($videoSource->metadata) ? $videoSource->metadata : [];
        foreach ([
            $metadata['download_url'] ?? null,
            $metadata['download_mp4_url'] ?? null,
            $metadata['mp4_play_url'] ?? null,
            $metadata['mp4_url'] ?? null,
            $metadata['original_url'] ?? null,
            $metadata['public_url'] ?? null,
            $metadata['source_url'] ?? null,
            $videoSource->full_url,
            $videoSource->url,
            $videoSource->file_path,
        ] as $url) {
            if (is_string($url) && trim($url) !== '') {
                return trim($url);
            }
        }

        return null;
    }

    private function usesDownloadUrlColumn(VideoSource $videoSource): bool
    {
        return in_array($videoSource->type, [
            'url',
            'direct',
            'upload',
            'uploaded',
            'cdn',
            'legacy_cdn',
            'contabo',
            'contabo_object_storage',
            'tele_ob',
            'bunny_stream',
            'nbx-engine',
        ], true);
    }
}
