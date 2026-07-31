<?php

namespace App\Filament\Resources\CreatorPermissionResource\Pages;

use App\Filament\Resources\CreatorPermissionResource;
use App\Services\CreatorAuditService;
use Filament\Resources\Pages\CreateRecord;

class CreateCreatorPermission extends CreateRecord
{
    protected static string $resource = CreatorPermissionResource::class;

    protected function afterCreate(): void
    {
        app(CreatorAuditService::class)->record(
            'creator.permissions_assigned',
            auth()->user(),
            $this->record->user,
            $this->record,
            [],
            $this->record->toArray()
        );
    }
}
