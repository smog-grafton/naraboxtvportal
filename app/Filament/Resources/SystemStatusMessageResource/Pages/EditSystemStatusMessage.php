<?php
namespace App\Filament\Resources\SystemStatusMessageResource\Pages;
use App\Filament\Resources\SystemStatusMessageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
class EditSystemStatusMessage extends EditRecord
{
    protected static string $resource = SystemStatusMessageResource::class;
    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->requiresConfirmation()->modalDescription('Saving creates a new message revision and makes changed text visible to targeted users.');
    }
}
