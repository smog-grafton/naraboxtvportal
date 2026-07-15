<?php

namespace App\Filament\Resources\CommunicationCampaignResource\Pages;

use App\Filament\Resources\CommunicationCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommunicationCampaigns extends ListRecords
{
    protected static string $resource = CommunicationCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
