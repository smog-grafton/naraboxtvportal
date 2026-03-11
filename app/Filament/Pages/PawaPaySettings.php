<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PawaPaySettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'PawaPay Settings';

    protected static ?string $title = 'PawaPay Settings';

    protected static ?string $navigationGroup = 'Payment Management';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.pawa-pay-settings';

    public function getCallbackUrls(): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return [
            'deposit' => $baseUrl . '/api/v1/webhooks/pawapay/deposits',
            'refund' => $baseUrl . '/api/v1/webhooks/pawapay/refunds',
        ];
    }
}

