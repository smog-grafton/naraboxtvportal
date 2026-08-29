<?php

namespace App\Filament\Resources\Concerns;

use App\Models\VideoSource;
use App\Services\LegacyCdnMigrationService;
use Filament\Notifications\Notification;
use Filament\Tables;

trait ManagesLegacyCdnMigrations
{
    protected function legacyCdnMigrationAction(string $assetType): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('migrate_to_nbx')
            ->label('Migrate to NBX')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Migrate legacy CDN source to NBX')
            ->modalDescription('NBX will use the direct physical legacy CDN storage path, validate the media, and retain the legacy source.')
            ->visible(fn (VideoSource $record): bool => app(LegacyCdnMigrationService::class)->canMigrate($record))
            ->action(function (VideoSource $record) use ($assetType): void {
                try {
                    $result = app(LegacyCdnMigrationService::class)->start($record, $assetType);
                    $notification = Notification::make()->title($result['message']);

                    if ($result['status'] === 'started') {
                        $notification->success();
                    } elseif ($result['status'] === 'already_running' || $result['status'] === 'already_migrated') {
                        $notification->warning();
                    } else {
                        $notification->info();
                    }

                    $notification->send();
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('NBX migration could not start')
                        ->body($exception->getMessage())
                        ->send();
                }
            });
    }
}
