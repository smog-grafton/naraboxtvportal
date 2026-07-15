<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CommentResource;
use App\Filament\Resources\MovieResource;
use App\Filament\Resources\TVShowResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VJClaimRequestResource;
use App\Filament\Resources\VJResource;
use App\Models\Comment;
use App\Models\Movie;
use App\Models\TVShow;
use App\Models\User;
use App\Models\VJ;
use App\Models\VJClaimRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected static ?string $pollingInterval = '60s';

    protected ?string $heading = 'Users and Content';

    protected ?string $description = 'Core platform totals across accounts, content, creators, and engagement.';

    protected function getStats(): array
    {
        $today = today();

        return [
            Stat::make('Total Users', number_format(User::count()))
                ->description('All user accounts in the system')
                ->descriptionIcon('heroicon-m-users')
                ->icon('heroicon-o-users')
                ->color('primary')
                ->url(UserResource::getUrl('index')),
            Stat::make('Joined Today', number_format(User::whereDate('created_at', $today)->count()))
                ->description('New users created on ' . $today->format('M j, Y'))
                ->descriptionIcon('heroicon-m-user-plus')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->url(UserResource::getUrl('index')),
            Stat::make('Movies', number_format(Movie::count()))
                ->description('Movies currently stored in the platform')
                ->descriptionIcon('heroicon-m-film')
                ->icon('heroicon-o-film')
                ->color('warning')
                ->url(MovieResource::getUrl('index')),
            Stat::make('TV Shows', number_format(TVShow::count()))
                ->description('TV show records currently available')
                ->descriptionIcon('heroicon-m-tv')
                ->icon('heroicon-o-tv')
                ->color('info')
                ->url(TVShowResource::getUrl('index')),
            Stat::make('VJs', number_format(VJ::count()))
                ->description('Registered VJ creator profiles')
                ->descriptionIcon('heroicon-m-microphone')
                ->icon('heroicon-o-microphone')
                ->color('gray')
                ->url(VJResource::getUrl('index')),
            Stat::make('Comments', number_format(Comment::count()))
                ->description('User comments posted on content')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('primary')
                ->url(CommentResource::getUrl('index')),
            Stat::make('VJ Claims', number_format(VJClaimRequest::count()))
                ->description('Claim requests raised by creators')
                ->descriptionIcon('heroicon-m-document-check')
                ->icon('heroicon-o-document-check')
                ->color('danger')
                ->url(VJClaimRequestResource::getUrl('index')),
        ];
    }
}
