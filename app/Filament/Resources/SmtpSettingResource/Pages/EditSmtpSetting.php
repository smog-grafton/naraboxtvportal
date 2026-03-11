<?php

namespace App\Filament\Resources\SmtpSettingResource\Pages;

use App\Filament\Resources\SmtpSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\SmtpSetting;

class EditSmtpSetting extends EditRecord
{
    protected static string $resource = SmtpSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If this setting is being activated, deactivate all others
        if ($data['is_active'] ?? false) {
            SmtpSetting::where('id', '!=', $this->record->id)
                ->update(['is_active' => false]);
        }

        return $data;
    }
}
