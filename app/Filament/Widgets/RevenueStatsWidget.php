<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PaymentTransactionResource;
use App\Models\PaymentTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '60s';

    protected ?string $heading = 'Revenue Overview';

    protected ?string $description = 'Revenue is calculated from successful payment transactions only.';

    protected function getStats(): array
    {
        $successfulTransactions = PaymentTransaction::query()->where('status', 'SUCCESS');
        $today = today();
        $now = now();

        $todayRevenue = (clone $successfulTransactions)
            ->whereDate('created_at', $today)
            ->sum('amount');

        $monthlyRevenue = (clone $successfulTransactions)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->sum('amount');

        $overallRevenue = (clone $successfulTransactions)->sum('amount');
        $successfulCount = (clone $successfulTransactions)->count();

        return [
            Stat::make("Today's Revenue", $this->formatCurrency($todayRevenue))
                ->description('Successful transactions on ' . $today->format('M j, Y'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->url(PaymentTransactionResource::getUrl('index')),
            Stat::make('Monthly Revenue', $this->formatCurrency($monthlyRevenue))
                ->description('Successful transactions in ' . $now->format('F Y'))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->icon('heroicon-o-chart-bar')
                ->color('primary')
                ->url(PaymentTransactionResource::getUrl('index')),
            Stat::make('Overall Revenue', $this->formatCurrency($overallRevenue))
                ->description('Total successful transaction value')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->icon('heroicon-o-currency-dollar')
                ->color('warning')
                ->url(PaymentTransactionResource::getUrl('index')),
            Stat::make('Successful Transactions', number_format($successfulCount))
                ->description('Completed payment transactions in the system')
                ->descriptionIcon('heroicon-m-check-badge')
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->url(PaymentTransactionResource::getUrl('index')),
        ];
    }

    private function formatCurrency(float | int | string $amount): string
    {
        return 'UGX ' . number_format((float) $amount, 2);
    }
}
