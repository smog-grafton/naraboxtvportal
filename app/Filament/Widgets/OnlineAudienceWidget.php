<?php

namespace App\Filament\Widgets;

use App\Services\PresenceService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OnlineAudienceWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '30s';

    protected ?string $heading = 'Audience Online';

    protected ?string $description = 'Explicit foreground heartbeats only; normal API requests are not counted as online activity.';

    protected function getStats(): array
    {
        $snapshot = app(PresenceService::class)->snapshot();
        $platforms = $snapshot['platforms'];

        return [
            Stat::make('People Online', number_format($snapshot['people_online']))
                ->description('Unique accounts plus stable guest identities')
                ->descriptionIcon('heroicon-m-signal')
                ->icon('heroicon-o-signal')
                ->color('success'),
            Stat::make('Authenticated', number_format($snapshot['unique_accounts_online']))
                ->description('Unique signed-in accounts with a live heartbeat')
                ->descriptionIcon('heroicon-m-user-circle')
                ->icon('heroicon-o-user-circle')
                ->color('primary'),
            Stat::make('Guests', number_format($snapshot['guests_online']))
                ->description('Stable anonymous browser/device identities')
                ->descriptionIcon('heroicon-m-user-plus')
                ->icon('heroicon-o-user-plus')
                ->color('warning'),
            Stat::make('Active Devices', number_format($snapshot['active_devices']))
                ->description('Heartbeating browser/device identities')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('gray'),
            Stat::make('Web', number_format($platforms['web'] ?? 0))
                ->description('Visible browser sessions')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('info'),
            Stat::make('Android', number_format($platforms['android'] ?? 0))
                ->description('Foreground Android sessions')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('success'),
            Stat::make('iOS', number_format($platforms['ios'] ?? 0))
                ->description('Foreground iOS sessions')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('primary'),
            Stat::make('Android TV', number_format($platforms['android_tv'] ?? 0))
                ->description('Foreground Android TV sessions')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->icon('heroicon-o-globe-alt')
                ->color('warning'),
        ];
    }
}
