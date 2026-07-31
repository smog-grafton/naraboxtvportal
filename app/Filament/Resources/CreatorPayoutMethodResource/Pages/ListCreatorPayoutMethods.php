<?php

namespace App\Filament\Resources\CreatorPayoutMethodResource\Pages;

use App\Filament\Resources\CreatorPayoutMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCreatorPayoutMethods extends ListRecords
{
    protected static string $resource = CreatorPayoutMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
