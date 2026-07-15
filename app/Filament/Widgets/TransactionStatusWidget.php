<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PaymentTransactionResource;
use App\Models\PaymentTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TransactionStatusWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected static ?string $pollingInterval = '60s';

    protected ?string $heading = 'Transaction Status';

    protected ?string $description = 'A live snapshot of the most important payment states to review.';

    protected function getStats(): array
    {
        return [
            Stat::make('Pending Transactions', number_format(PaymentTransaction::where('status', 'PENDING')->count()))
                ->description('Transactions waiting for completion or review')
                ->descriptionIcon('heroicon-m-clock')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->url(PaymentTransactionResource::getUrl('index')),
            Stat::make('Failed Transactions', number_format(PaymentTransaction::where('status', 'FAILED')->count()))
                ->description('Transactions that did not complete successfully')
                ->descriptionIcon('heroicon-m-x-circle')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->url(PaymentTransactionResource::getUrl('index')),
            Stat::make('Cancelled Transactions', number_format(PaymentTransaction::where('status', 'CANCELLED')->count()))
                ->description('Transactions cancelled before completion')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->icon('heroicon-o-no-symbol')
                ->color('gray')
                ->url(PaymentTransactionResource::getUrl('index')),
            Stat::make('All Transactions', number_format(PaymentTransaction::count()))
                ->description('Full payment transaction volume')
                ->descriptionIcon('heroicon-m-queue-list')
                ->icon('heroicon-o-queue-list')
                ->color('primary')
                ->url(PaymentTransactionResource::getUrl('index')),
        ];
    }
}
