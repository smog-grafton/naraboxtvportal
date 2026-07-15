<?php

namespace App\Filament\Resources\ContaboObjectStorageBucketResource\Pages;

use App\Filament\Resources\ContaboObjectStorageBucketResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContaboObjectStorageBucket extends EditRecord
{
    protected static string $resource = ContaboObjectStorageBucketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
