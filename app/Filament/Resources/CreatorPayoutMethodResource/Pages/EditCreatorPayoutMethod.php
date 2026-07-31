<?php

namespace App\Filament\Resources\CreatorPayoutMethodResource\Pages;

use App\Filament\Resources\CreatorPayoutMethodResource;
use Filament\Resources\Pages\EditRecord;

class EditCreatorPayoutMethod extends EditRecord
{
    protected static string $resource = CreatorPayoutMethodResource::class;

    protected function afterSave(): void
    {
        if ($this->record->automation_type === 'automatic') {
            $this->record->refreshConfigurationStatus();
        }
    }
}
