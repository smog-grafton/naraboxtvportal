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

        $query = VideoSource::whereIn('type', ['url', 'fetched', 'local'])
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
            // Check if download source already exists
            $existing = DownloadSource::where('downloadable_type', $videoSource->sourceable_type)
                ->where('downloadable_id', $videoSource->sourceable_id)
                ->where('type', $videoSource->type)
                ->where(function($q) use ($videoSource) {
                    if ($videoSource->type === 'url' && $videoSource->url) {
                        $q->where('url', $videoSource->url);
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
                'url' => $videoSource->type === 'url' ? $videoSource->url : null,
                'file_path' => in_array($videoSource->type, ['fetched', 'local']) ? $videoSource->file_path : null,
                'quality' => $videoSource->quality ?: 'auto',
                'format' => $videoSource->format ?: 'mp4',
                'file_size' => $videoSource->file_size,
                'label' => ($videoSource->quality ?: 'auto') . ' ' . strtoupper($videoSource->format ?: 'mp4'),
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
}
