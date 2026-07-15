<?php

namespace App\Filament\Widgets;

use App\Models\CommunicationLog;
use App\Models\ContentRequest;
use App\Models\MediaPlaybackReport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommunicationOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return [
            Stat::make('Emails Sent Today', number_format(CommunicationLog::query()->whereDate('sent_at', today())->count()))
                ->color('success'),
            Stat::make('Email Failures Today', number_format(CommunicationLog::query()->whereDate('failed_at', today())->count()))
                ->color('danger'),
            Stat::make('Pending Requests', number_format(ContentRequest::query()->where('status', 'pending')->count()))
                ->color('warning'),
            Stat::make('Open Playback Issues', number_format(MediaPlaybackReport::query()->where('status', 'open')->count()))
                ->color('info'),
        ];
    }
}
