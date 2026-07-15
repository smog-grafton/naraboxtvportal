<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\ArrayInput;

class RestoreContaboVideoSources extends Command
{
    protected $signature = 'video-sources:restore-contabo
        {--dry-run : Report changes without writing}
        {--limit=100 : Maximum video sources to inspect}
        {--movie-id= : Restrict to one movie ID}
        {--episode-id= : Restrict to one episode ID}
        {--activate-valid : Reactivate inactive Contabo sources with usable non-HLS URLs}
        {--sync-downloads : Sync usable MP4 sources to download_sources when downloads are enabled}';

    protected $description = 'Compatibility shortcut for restoring old Contabo Object Storage video sources';

    public function handle(): int
    {
        $command = $this->getApplication()?->find('video-sources:restore-legacy');

        if (! $command) {
            $this->error('video-sources:restore-legacy is not registered.');
            return self::FAILURE;
        }

        $input = new ArrayInput(array_filter([
            'command' => 'video-sources:restore-legacy',
            '--dry-run' => (bool) $this->option('dry-run') ?: null,
            '--limit' => (string) $this->option('limit'),
            '--source-type' => ['contabo', 'contabo_object_storage'],
            '--movie-id' => $this->option('movie-id') ?: null,
            '--episode-id' => $this->option('episode-id') ?: null,
            '--activate-valid' => (bool) $this->option('activate-valid') ?: null,
            '--sync-downloads' => (bool) $this->option('sync-downloads') ?: null,
        ], fn ($value) => $value !== null));

        return $command->run($input, $this->output);
    }
}
