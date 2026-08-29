<?php

namespace App\Filament\Widgets;

use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CreatorPartnerOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 7;

    protected ?string $heading = 'Creator and Partner Programs';

    protected ?string $description = 'Workflow health and canonical wallet balances, with partner balances separated from creator balances.';

    protected function getStats(): array
    {
        $metrics = app(DashboardMetricsService::class)->snapshot();
        $creators = $metrics['creators'];
        $partners = $metrics['partners'];

        return [
            Stat::make('Verified Creators', number_format($creators['verified_creators']))
                ->description(sprintf('%d approved applications · %d pending', $creators['approved_applications'], $creators['pending_applications']))
                ->descriptionIcon('heroicon-m-check-badge')->icon('heroicon-o-check-badge')->color('success'),
            Stat::make('Creator Available', $this->money($creators['available_minor']))
                ->description('Creator wallet ledger available balance')
                ->descriptionIcon('heroicon-m-wallet')->icon('heroicon-o-wallet')->color('primary'),
            Stat::make('Active Partners', number_format($partners['active_partners']))
                ->description(number_format($partners['referred_users']).' attributed users')
                ->descriptionIcon('heroicon-m-user-group')->icon('heroicon-o-user-group')->color('info'),
            Stat::make('Partner Available', $this->money($partners['available_minor']))
                ->description('Partner wallet ledger available balance')
                ->descriptionIcon('heroicon-m-banknotes')->icon('heroicon-o-banknotes')->color('warning'),
        ];
    }

    private function money(int|float|string $amount): string
    {
        return 'UGX '.number_format((float) $amount, 0);
    }
}
