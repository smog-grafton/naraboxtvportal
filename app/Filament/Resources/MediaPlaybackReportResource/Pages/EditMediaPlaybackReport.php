<?php

namespace App\Filament\Resources\MediaPlaybackReportResource\Pages;

use App\Filament\Resources\MediaPlaybackReportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMediaPlaybackReport extends EditRecord
{
    protected static string $resource = MediaPlaybackReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
