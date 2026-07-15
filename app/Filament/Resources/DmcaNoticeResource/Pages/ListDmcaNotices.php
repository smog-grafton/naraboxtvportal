<?php

namespace App\Filament\Resources\DmcaNoticeResource\Pages;

use App\Filament\Resources\DmcaNoticeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDmcaNotices extends ListRecords
{
    protected static string $resource = DmcaNoticeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

