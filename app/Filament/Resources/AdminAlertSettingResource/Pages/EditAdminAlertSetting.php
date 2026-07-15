<?php

namespace App\Filament\Resources\AdminAlertSettingResource\Pages;

use App\Filament\Resources\AdminAlertSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdminAlertSetting extends EditRecord
{
    protected static string $resource = AdminAlertSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
