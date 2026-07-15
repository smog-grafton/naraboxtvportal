<?php

namespace App\Filament\Resources\EditorialCategoryResource\Pages;

use App\Filament\Resources\EditorialCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEditorialCategories extends ListRecords
{
    protected static string $resource = EditorialCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
