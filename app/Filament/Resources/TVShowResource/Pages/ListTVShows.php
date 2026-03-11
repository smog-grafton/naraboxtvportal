<?php

namespace App\Filament\Resources\TVShowResource\Pages;

use App\Filament\Resources\TVShowResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTVShows extends ListRecords
{
    protected static string $resource = TVShowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
