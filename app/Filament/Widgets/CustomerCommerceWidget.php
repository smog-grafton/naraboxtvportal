<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PaymentTransactionResource;
use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerCommerceWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected static ?string $pollingInterval = '60s';

    protected ?string $heading = 'Customer Commerce';

    protected ?string $description = 'Unique customers and active access from the rental and purchase ledgers.';

    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsService::class)->snapshot();
        $rentals = $metrics['rentals'];
        $purchases = $metrics['purchases'];

        return [
            Stat::make('Rental Users', number_format($rentals['unique_users']))
                ->description(sprintf('%d active rentals · %d this month', $rentals['active_items'] ?? 0, $rentals['month_users']))
                ->descriptionIcon('heroicon-m-film')->icon('heroicon-o-film')->color('warning'),
            Stat::make('Purchase Users', number_format($purchases['unique_users']))
                ->description(sprintf('%d purchases this month', $purchases['month_users']))
                ->descriptionIcon('heroicon-m-shopping-bag')->icon('heroicon-o-shopping-bag')->color('success'),
            Stat::make('Active Rentals', number_format($rentals['active_items'] ?? 0))
                ->description('Current rental entitlements not expired')
                ->descriptionIcon('heroicon-m-clock')->icon('heroicon-o-clock')->color('info'),
            Stat::make('Commerce Revenue', $this->money($metrics['revenue']['month']))
                ->description('Successful rent + buy transactions this month')
                ->descriptionIcon('heroicon-m-banknotes')->icon('heroicon-o-banknotes')->color('primary')
                ->url(PaymentTransactionResource::getUrl('index')),
        ];
    }

    private function money(float|int|string $amount): string
    {
        return 'UGX '.number_format((float) $amount, 0);
    }
}
