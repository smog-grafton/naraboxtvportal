<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PaymentTransactionResource;
use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '60s';

    protected ?string $heading = 'Revenue Overview';

    protected ?string $description = 'Successful transactions grouped by actual type and reconciled with canonical creator/partner allocations.';

    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsService::class)->snapshot()['revenue'];
        $byType = $metrics['by_type'];

        return [
            Stat::make("Today's Revenue", $this->formatCurrency($metrics['today']))
                ->description('Successful transactions since the start of today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->url(PaymentTransactionResource::getUrl('index')),
            Stat::make('Monthly Revenue', $this->formatCurrency($metrics['month']))
                ->description('Successful transactions since the start of this month')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->icon('heroicon-o-chart-bar')
                ->color('primary')
                ->url(PaymentTransactionResource::getUrl('index')),
            Stat::make('Retained Revenue', $this->formatCurrency($metrics['retained']))
                ->description('Gross less canonical creator and partner allocations')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->icon('heroicon-o-currency-dollar')
                ->color('warning')
                ->url(PaymentTransactionResource::getUrl('index')),
            Stat::make('Revenue by Type', $this->formatBreakdown($byType))
                ->description('Actual transaction categories from successful payments')
                ->descriptionIcon('heroicon-m-check-badge')
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->url(PaymentTransactionResource::getUrl('index')),
        ];
    }

    private function formatCurrency(float | int | string $amount): string
    {
        return 'UGX ' . number_format((float) $amount, 0);
    }

    private function formatBreakdown(array $byType): string
    {
        if ($byType === []) {
            return 'UGX 0';
        }

        return collect($byType)
            ->map(fn ($amount, $type): string => $type . ' ' . $this->formatCurrency($amount))
            ->implode(' · ');
    }
}
