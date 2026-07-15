<?php

namespace App\Filament\Resources\AdBannerResource\Pages;

use App\Filament\Resources\AdBannerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdBanner extends EditRecord
{
    protected static string $resource = AdBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

