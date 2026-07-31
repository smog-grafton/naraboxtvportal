<?php

namespace App\Filament\Resources\CreatorPermissionResource\Pages;

use App\Filament\Resources\CreatorPermissionResource;
use Filament\Resources\Pages\EditRecord;

class EditCreatorPermission extends EditRecord
{
    protected static string $resource = CreatorPermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
