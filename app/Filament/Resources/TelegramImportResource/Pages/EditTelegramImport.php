<?php

namespace App\Filament\Resources\TelegramImportResource\Pages;

use App\Filament\Resources\TelegramImportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTelegramImport extends EditRecord
{
    protected static string $resource = TelegramImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
