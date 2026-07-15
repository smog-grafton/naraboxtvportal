<?php

namespace App\Filament\Resources\ContentRequestResource\Pages;

use App\Filament\Resources\ContentRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContentRequests extends ListRecords
{
    protected static string $resource = ContentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
