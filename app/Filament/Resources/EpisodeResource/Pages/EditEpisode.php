<?php

namespace App\Filament\Resources\EpisodeResource\Pages;

use App\Events\EpisodePublished;
use App\Filament\Resources\EpisodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEpisode extends EditRecord
{
    protected static string $resource = EpisodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        event(new EpisodePublished($this->record));
    }
}
