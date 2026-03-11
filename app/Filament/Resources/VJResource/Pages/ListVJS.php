<?php

namespace App\Filament\Resources\VJResource\Pages;

use App\Filament\Resources\VJResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVJS extends ListRecords
{
    protected static string $resource = VJResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
