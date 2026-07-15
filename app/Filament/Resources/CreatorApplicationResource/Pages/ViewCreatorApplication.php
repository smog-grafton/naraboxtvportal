<?php

namespace App\Filament\Resources\CreatorApplicationResource\Pages;

use App\Filament\Resources\CreatorApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCreatorApplication extends ViewRecord
{
    protected static string $resource = CreatorApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
