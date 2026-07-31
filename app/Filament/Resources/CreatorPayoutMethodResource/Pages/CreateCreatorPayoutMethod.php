<?php

namespace App\Filament\Resources\CreatorPayoutMethodResource\Pages;

use App\Filament\Resources\CreatorPayoutMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCreatorPayoutMethod extends CreateRecord
{
    protected static string $resource = CreatorPayoutMethodResource::class;

    protected function afterCreate(): void
    {
        if ($this->record->automation_type === 'automatic') {
            $this->record->refreshConfigurationStatus();
        }
    }
}
