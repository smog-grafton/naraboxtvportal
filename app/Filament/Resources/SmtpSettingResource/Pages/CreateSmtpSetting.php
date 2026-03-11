<?php

namespace App\Filament\Resources\SmtpSettingResource\Pages;

use App\Filament\Resources\SmtpSettingResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\SmtpSetting;

class CreateSmtpSetting extends CreateRecord
{
    protected static string $resource = SmtpSettingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If this setting is being activated, deactivate all others
        if ($data['is_active'] ?? false) {
            SmtpSetting::query()->update(['is_active' => false]);
        }

        return $data;
    }
}
