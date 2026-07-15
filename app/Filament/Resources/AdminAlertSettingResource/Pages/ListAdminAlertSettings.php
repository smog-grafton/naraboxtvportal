<?php

namespace App\Filament\Resources\AdminAlertSettingResource\Pages;

use App\Filament\Resources\AdminAlertSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdminAlertSettings extends ListRecords
{
    protected static string $resource = AdminAlertSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
