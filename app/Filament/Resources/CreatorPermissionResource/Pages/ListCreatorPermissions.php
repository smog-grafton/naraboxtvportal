<?php

namespace App\Filament\Resources\CreatorPermissionResource\Pages;

use App\Filament\Resources\CreatorPermissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCreatorPermissions extends ListRecords
{
    protected static string $resource = CreatorPermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Assign creator permissions'),
        ];
    }
}
