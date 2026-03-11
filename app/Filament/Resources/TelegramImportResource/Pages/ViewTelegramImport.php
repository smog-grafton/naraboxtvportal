<?php

namespace App\Filament\Resources\TelegramImportResource\Pages;

use App\Filament\Resources\TelegramImportResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTelegramImport extends ViewRecord
{
    protected static string $resource = TelegramImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('create_movie')
                ->label('Create Movie from this')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => \App\Filament\Resources\MovieResource::getUrl('create', [
                    'title' => $this->record->title_guess,
                    'vj' => $this->record->vj_guess,
                    'telegram_import_id' => $this->record->id,
                ])),
        ];
    }
}
