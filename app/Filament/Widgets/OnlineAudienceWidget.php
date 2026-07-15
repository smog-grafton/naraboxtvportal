<?php

namespace App\Filament\Widgets;

use App\Models\OnlineVisitor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OnlineAudienceWidget extends StatsOverviewWidget
{
    private const ACTIVE_WINDOW_MINUTES = 5;

    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = '30s';

    protected ?string $heading = 'Audience Online';

    protected ?string $description = 'Live presence across the app and website, based on activity seen in the last 5 minutes.';

    protected function getStats(): array
    {
        if (! Schema::hasTable('online_visitors')) {
            return $this->emptyStats('Run the latest migration to enable live audience tracking.');
        }

        $activeVisitors = OnlineVisitor::query()
            ->active(now()->subMinutes(self::ACTIVE_WINDOW_MINUTES))
            ->get(['visitor_key', 'user_id', 'platform']);

        $loggedIn = $activeVisitors
            ->filter(fn (OnlineVisitor $visitor) => filled($visitor->user_id))
            ->pluck('user_id')
            ->unique()
            ->count();

        $guests = $activeVisitors
            ->filter(fn (OnlineVisitor $visitor) => blank($visitor->user_id))
            ->count();

        $inApp = $this->countUniqueVisitors(
            $activeVisitors->filter(fn (OnlineVisitor $visitor) => $visitor->platform === 'app')
        );

        $onWebsite = $this->countUniqueVisitors(
            $activeVisitors->filter(fn (OnlineVisitor $visitor) => $visitor->platform === 'web')
        );

        return [
            Stat::make('Total Online', number_format($this->countUniqueVisitors($activeVisitors)))
                ->description("Seen in the last " . self::ACTIVE_WINDOW_MINUTES . ' minutes')
                ->descriptionIcon('heroicon-m-signal')
                ->icon('heroicon-o-signal')
                ->color('success'),
            Stat::make('Logged In', number_format($loggedIn))
                ->description('Authenticated users active across app and web')
                ->descriptionIcon('heroicon-m-user-circle')
                ->icon('heroicon-o-user-circle')
                ->color('primary'),
            Stat::make('Guests', number_format($guests))
                ->description('Visitors active without logging in')
                ->descriptionIcon('heroicon-m-user-plus')
                ->icon('heroicon-o-user-plus')
                ->color('warning'),
            Stat::make('In App', number_format($inApp))
                ->description('Active API and app traffic')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('info'),
            Stat::make('On Website', number_format($onWebsite))
                ->description('Active website and browser traffic')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->icon('heroicon-o-globe-alt')
                ->color('gray'),
        ];
    }

    private function countUniqueVisitors(Collection $visitors): int
    {
        return $visitors
            ->map(fn (OnlineVisitor $visitor) => $visitor->user_id
                ? 'user:' . $visitor->user_id
                : 'guest:' . $visitor->visitor_key)
            ->unique()
            ->count();
    }

    private function emptyStats(string $description): array
    {
        return [
            Stat::make('Total Online', '0')
                ->description($description)
                ->descriptionIcon('heroicon-m-information-circle')
                ->icon('heroicon-o-signal')
                ->color('gray'),
        ];
    }
}
