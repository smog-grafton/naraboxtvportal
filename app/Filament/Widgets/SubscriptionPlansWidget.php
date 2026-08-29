<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubscriptionPlansWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 6;

    protected ?string $heading = 'Active Subscribers by Plan';

    protected ?string $description = 'Plan labels and counts come from the subscription plan records; no plan names are hardcoded.';

    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsService::class)->snapshot()['subscriptions'];
        $stats = [
            Stat::make('All Active Subscribers', number_format($metrics['active_users']))
                ->description('Deduplicated users with a currently valid entitlement')
                ->descriptionIcon('heroicon-m-users')->icon('heroicon-o-users')->color('success'),
        ];

        foreach ($metrics['plans'] as $plan) {
            $duration = $plan['duration_days'] !== null ? ' · '.$plan['duration_days'].' days' : '';
            $stats[] = Stat::make((string) $plan['label'], number_format((int) $plan['users']))
                ->description('Active users'.$duration)
                ->descriptionIcon('heroicon-m-credit-card')->icon('heroicon-o-credit-card')
                ->color($plan['users'] > 0 ? 'primary' : 'gray');
        }

        return $stats;
    }
}
