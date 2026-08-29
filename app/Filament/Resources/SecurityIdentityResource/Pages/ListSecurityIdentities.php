<?php

namespace App\Filament\Resources\SecurityIdentityResource\Pages;

use App\Filament\Resources\SecurityIdentityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSecurityIdentities extends ListRecords
{
    protected static string $resource = SecurityIdentityResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
