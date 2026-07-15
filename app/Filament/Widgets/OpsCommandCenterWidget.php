<?php

namespace App\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Artisan;

class OpsCommandCenterWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.ops-command-center-widget';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    private function notifyCommandResult(string $title, string $output, bool $success = true): void
    {
        $notification = Notification::make()
            ->title($title)
            ->body($output !== '' ? str($output)->limit(1200)->toString() : 'Command finished with no output.');

        ($success ? $notification->success() : $notification->warning())->send();
    }

    public function syncCdnReadinessAction(): Action
    {
        return Action::make('syncCdnReadiness')
            ->label('Sync CDN readiness')
            ->icon('heroicon-o-signal')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Checks CDN/NBX generated MP4/HLS sources and promotes ready playback URLs. Optionally asks CDN to queue missing optimization.')
            ->form([
                TextInput::make('limit')->numeric()->minValue(1)->maxValue(1000)->default(300),
                Toggle::make('queue_missing')->label('Queue missing optimization on CDN')->default(true),
                Toggle::make('force')->label('Ignore readiness TTL')->default(false),
            ])
            ->action(function (array $data): void {
                Artisan::call('cdn:sync-playback-readiness', [
                    '--limit' => max(1, min(1000, (int) ($data['limit'] ?? 300))),
                    '--queue-missing' => (bool) ($data['queue_missing'] ?? true),
                    '--force' => (bool) ($data['force'] ?? false),
                ]);

                $this->notifyCommandResult('CDN readiness synced', trim(Artisan::output()));
            });
    }

    public function syncNbxSourcesAction(): Action
    {
        return Action::make('syncNbxSources')
            ->label('Sync NBX sources')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('info')
            ->requiresConfirmation()
            ->form([
                TextInput::make('movie_id')->label('Movie ID')->numeric()->minValue(1),
                TextInput::make('episode_id')->label('Episode ID')->numeric()->minValue(1),
            ])
            ->action(function (array $data): void {
                $args = [];
                if (! empty($data['movie_id'])) {
                    $args['--movie-id'] = (int) $data['movie_id'];
                }
                if (! empty($data['episode_id'])) {
                    $args['--episode-id'] = (int) $data['episode_id'];
                }

                Artisan::call('nbx:sync-video-sources', $args);

                $this->notifyCommandResult('NBX sources synced', trim(Artisan::output()));
            });
    }

    public function backfillContaboNbxAction(): Action
    {
        return Action::make('backfillContaboNbx')
            ->label('Backfill Contabo → NBX')
            ->icon('heroicon-o-cloud-arrow-up')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Schedules old Contabo object-storage video files for NBX faststart + 480p HLS. Dry run is enabled by default.')
            ->form([
                TextInput::make('limit')->numeric()->minValue(1)->maxValue(200)->default(20),
                TextInput::make('movie_id')->label('Movie ID')->numeric()->minValue(1),
                Toggle::make('include_720p')->label('Request 720p when source supports it')->default(false),
                Toggle::make('dry_run')->label('Dry run only')->default(true),
                Toggle::make('force')->label('Force duplicate resubmission')->default(false),
            ])
            ->action(function (array $data): void {
                $args = [
                    '--limit' => max(1, min(200, (int) ($data['limit'] ?? 20))),
                    '--include-720p' => (bool) ($data['include_720p'] ?? false),
                    '--dry-run' => (bool) ($data['dry_run'] ?? true),
                    '--force' => (bool) ($data['force'] ?? false),
                ];

                if (! empty($data['movie_id'])) {
                    $args['--movie-id'] = (int) $data['movie_id'];
                }

                Artisan::call('nbx:backfill-contabo-sources', $args);

                $this->notifyCommandResult('Contabo NBX backfill finished', trim(Artisan::output()));
            });
    }

    public function diagnoseVideoSourcesAction(): Action
    {
        return Action::make('diagnoseVideoSources')
            ->label('Diagnose video sources')
            ->icon('heroicon-o-magnifying-glass-circle')
            ->color('gray')
            ->modalDescription('Shows source type totals and why inspected movie/episode sources are playable or rejected.')
            ->form([
                TextInput::make('limit')->numeric()->minValue(1)->maxValue(500)->default(50),
                TextInput::make('source_type')->label('Source type')->helperText('Optional: contabo, fetched, curl, url, nbx-engine'),
                TextInput::make('movie_id')->label('Movie ID')->numeric()->minValue(1),
                TextInput::make('episode_id')->label('Episode ID')->numeric()->minValue(1),
                Toggle::make('only_broken')->label('Only broken')->default(true),
            ])
            ->action(function (array $data): void {
                $args = [
                    '--limit' => max(1, min(500, (int) ($data['limit'] ?? 50))),
                    '--only-broken' => (bool) ($data['only_broken'] ?? true),
                ];

                if (filled($data['source_type'] ?? null)) {
                    $args['--source-type'] = trim((string) $data['source_type']);
                }
                if (! empty($data['movie_id'])) {
                    $args['--movie-id'] = (int) $data['movie_id'];
                }
                if (! empty($data['episode_id'])) {
                    $args['--episode-id'] = (int) $data['episode_id'];
                }

                Artisan::call('video-sources:diagnose', $args);

                $this->notifyCommandResult('Video source diagnosis finished', trim(Artisan::output()));
            });
    }

    public function restoreLegacySourcesAction(): Action
    {
        return Action::make('restoreLegacySources')
            ->label('Restore legacy sources')
            ->icon('heroicon-o-wrench-screwdriver')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Emergency repair for old Contabo, fetched/cURL, local, direct, upload, CDN, Bunny and NBX sources. Dry run is enabled by default.')
            ->form([
                TextInput::make('limit')->numeric()->minValue(1)->maxValue(1000)->default(100),
                TextInput::make('source_type')->label('Source type')->helperText('Optional: contabo, fetched, curl, url, local, nbx-engine'),
                TextInput::make('movie_id')->label('Movie ID')->numeric()->minValue(1),
                TextInput::make('episode_id')->label('Episode ID')->numeric()->minValue(1),
                Toggle::make('dry_run')->label('Dry run only')->default(true),
                Toggle::make('activate_valid')->label('Reactivate valid legacy MP4 sources')->default(false),
                Toggle::make('sync_downloads')->label('Sync MP4 sources to downloads')->default(false),
            ])
            ->action(function (array $data): void {
                $args = [
                    '--limit' => max(1, min(1000, (int) ($data['limit'] ?? 100))),
                    '--dry-run' => (bool) ($data['dry_run'] ?? true),
                    '--activate-valid' => (bool) ($data['activate_valid'] ?? false),
                    '--sync-downloads' => (bool) ($data['sync_downloads'] ?? false),
                ];

                if (filled($data['source_type'] ?? null)) {
                    $args['--source-type'] = [trim((string) $data['source_type'])];
                }
                if (! empty($data['movie_id'])) {
                    $args['--movie-id'] = (int) $data['movie_id'];
                }
                if (! empty($data['episode_id'])) {
                    $args['--episode-id'] = (int) $data['episode_id'];
                }

                Artisan::call('video-sources:restore-legacy', $args);

                $this->notifyCommandResult('Legacy source restore finished', trim(Artisan::output()));
            });
    }

    public function restoreContaboSourcesAction(): Action
    {
        return Action::make('restoreContaboSources')
            ->label('Restore Contabo sources')
            ->icon('heroicon-o-cloud')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Shortcut for old Contabo/Contabo Object Storage sources only. Dry run is enabled by default.')
            ->form([
                TextInput::make('limit')->numeric()->minValue(1)->maxValue(1000)->default(100),
                TextInput::make('movie_id')->label('Movie ID')->numeric()->minValue(1),
                TextInput::make('episode_id')->label('Episode ID')->numeric()->minValue(1),
                Toggle::make('dry_run')->label('Dry run only')->default(true),
                Toggle::make('activate_valid')->label('Reactivate valid Contabo MP4 sources')->default(false),
                Toggle::make('sync_downloads')->label('Sync Contabo MP4 sources to downloads')->default(false),
            ])
            ->action(function (array $data): void {
                $args = [
                    '--limit' => max(1, min(1000, (int) ($data['limit'] ?? 100))),
                    '--dry-run' => (bool) ($data['dry_run'] ?? true),
                    '--activate-valid' => (bool) ($data['activate_valid'] ?? false),
                    '--sync-downloads' => (bool) ($data['sync_downloads'] ?? false),
                ];

                if (! empty($data['movie_id'])) {
                    $args['--movie-id'] = (int) $data['movie_id'];
                }
                if (! empty($data['episode_id'])) {
                    $args['--episode-id'] = (int) $data['episode_id'];
                }

                Artisan::call('video-sources:restore-contabo', $args);

                $this->notifyCommandResult('Contabo source restore finished', trim(Artisan::output()));
            });
    }

    public function repairVideoAvailabilityAction(): Action
    {
        return Action::make('repairVideoAvailability')
            ->label('Repair video availability')
            ->icon('heroicon-o-lifebuoy')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Runs the broader video-sources repair command. Dry run is enabled by default.')
            ->form([
                TextInput::make('limit')->numeric()->minValue(1)->maxValue(1000)->default(100),
                TextInput::make('movie_id')->label('Movie ID')->numeric()->minValue(1),
                TextInput::make('episode_id')->label('Episode ID')->numeric()->minValue(1),
                Toggle::make('dry_run')->label('Dry run only')->default(true),
                Toggle::make('activate_valid')->label('Reactivate valid non-HLS sources')->default(false),
                Toggle::make('sync_downloads')->label('Sync MP4 downloads')->default(false),
                Toggle::make('derive_cdn_siblings')->label('Derive CDN siblings')->default(false),
            ])
            ->action(function (array $data): void {
                $args = [
                    '--limit' => max(1, min(1000, (int) ($data['limit'] ?? 100))),
                    '--dry-run' => (bool) ($data['dry_run'] ?? true),
                    '--activate-valid' => (bool) ($data['activate_valid'] ?? false),
                    '--sync-downloads' => (bool) ($data['sync_downloads'] ?? false),
                    '--derive-cdn-siblings' => (bool) ($data['derive_cdn_siblings'] ?? false),
                ];

                if (! empty($data['movie_id'])) {
                    $args['--movie-id'] = (int) $data['movie_id'];
                }
                if (! empty($data['episode_id'])) {
                    $args['--episode-id'] = (int) $data['episode_id'];
                }

                Artisan::call('video-sources:repair-availability', $args);

                $this->notifyCommandResult('Video availability repair finished', trim(Artisan::output()));
            });
    }

    public function syncDownloadsAction(): Action
    {
        return Action::make('syncDownloads')
            ->label('Sync video downloads')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Sync active MP4-capable video sources into download_sources. HLS playlists are skipped.')
            ->form([
                TextInput::make('movie_id')->label('Movie ID')->numeric()->minValue(1),
                TextInput::make('episode_id')->label('Episode ID')->numeric()->minValue(1),
            ])
            ->action(function (array $data): void {
                $args = [];
                if (! empty($data['movie_id'])) {
                    $args['--movie-id'] = (int) $data['movie_id'];
                }
                if (! empty($data['episode_id'])) {
                    $args['--episode-id'] = (int) $data['episode_id'];
                }

                Artisan::call('videos:sync-downloads', $args);

                $this->notifyCommandResult('Download source sync finished', trim(Artisan::output()));
            });
    }

    public function runQueueAction(): Action
    {
        return Action::make('runQueue')
            ->label('Run queue now')
            ->icon('heroicon-o-play')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Runs a small queue worker batch in this request. Use low max jobs for heavy imports.')
            ->form([
                TextInput::make('queue')->default('default')->helperText('Examples: default, communications, contabo-imports'),
                TextInput::make('max_jobs')->label('Max jobs')->numeric()->minValue(1)->maxValue(50)->default(5),
                TextInput::make('timeout')->numeric()->minValue(60)->maxValue(21600)->default(3600),
            ])
            ->action(function (array $data): void {
                Artisan::call('queue:work', [
                    'connection' => 'database',
                    '--queue' => trim((string) ($data['queue'] ?? 'default')) ?: 'default',
                    '--max-jobs' => max(1, min(50, (int) ($data['max_jobs'] ?? 5))),
                    '--stop-when-empty' => true,
                    '--tries' => 1,
                    '--timeout' => max(60, min(21600, (int) ($data['timeout'] ?? 3600))),
                ]);

                $this->notifyCommandResult('Queue worker finished', trim(Artisan::output()));
            });
    }

    public function restartQueuesAction(): Action
    {
        return Action::make('restartQueues')
            ->label('Restart queues')
            ->icon('heroicon-o-power')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (): void {
                Artisan::call('queue:restart');

                $this->notifyCommandResult('Queue restart signal sent', trim(Artisan::output()));
            });
    }

    public function reconcilePaymentsAction(): Action
    {
        return Action::make('reconcilePayments')
            ->label('Reconcile payments')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (): void {
                $outputs = [];
                foreach (['iotec:reconcile-pending', 'pawapay:reconcile-pending'] as $command) {
                    Artisan::call($command);
                    $outputs[] = $command . ': ' . trim(Artisan::output());
                }

                $this->notifyCommandResult('Payment reconciliation finished', implode("\n\n", $outputs));
            });
    }

    public function expireSubscriptionsAction(): Action
    {
        return Action::make('expireSubscriptions')
            ->label('Expire subscriptions')
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (): void {
                Artisan::call('subscriptions:expire');

                $this->notifyCommandResult('Subscription expiry command finished', trim(Artisan::output()));
            });
    }
}
