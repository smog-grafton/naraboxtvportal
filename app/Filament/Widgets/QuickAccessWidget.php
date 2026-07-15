<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CommentResource;
use App\Filament\Resources\MovieResource;
use App\Filament\Resources\PaymentTransactionResource;
use App\Filament\Resources\TVShowResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VJClaimRequestResource;
use App\Filament\Resources\VJResource;
use Filament\Widgets\Widget;

class QuickAccessWidget extends Widget
{
    protected static string $view = 'filament.widgets.quick-access-widget';

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        return [
            'links' => [
                [
                    'label' => 'Users',
                    'description' => 'Manage accounts, plans, and roles.',
                    'icon' => 'heroicon-o-users',
                    'url' => UserResource::getUrl('index'),
                ],
                [
                    'label' => 'Transactions',
                    'description' => 'Review payment activity and status changes.',
                    'icon' => 'heroicon-o-currency-dollar',
                    'url' => PaymentTransactionResource::getUrl('index'),
                ],
                [
                    'label' => 'Movies',
                    'description' => 'Jump into the movie catalog.',
                    'icon' => 'heroicon-o-film',
                    'url' => MovieResource::getUrl('index'),
                ],
                [
                    'label' => 'TV Shows',
                    'description' => 'Manage series and related content.',
                    'icon' => 'heroicon-o-tv',
                    'url' => TVShowResource::getUrl('index'),
                ],
                [
                    'label' => 'VJs',
                    'description' => 'Open creator profiles and updates.',
                    'icon' => 'heroicon-o-microphone',
                    'url' => VJResource::getUrl('index'),
                ],
                [
                    'label' => 'VJ Claims',
                    'description' => 'Check ownership requests and reviews.',
                    'icon' => 'heroicon-o-document-check',
                    'url' => VJClaimRequestResource::getUrl('index'),
                ],
                [
                    'label' => 'Comments',
                    'description' => 'Moderate feedback from users.',
                    'icon' => 'heroicon-o-chat-bubble-left-right',
                    'url' => CommentResource::getUrl('index'),
                ],
            ],
        ];
    }
}
