<?php

namespace App\Filament\Resources\CommunicationCampaignResource\Pages;

use App\Filament\Resources\CommunicationCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommunicationCampaign extends EditRecord
{
    protected static string $resource = CommunicationCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
