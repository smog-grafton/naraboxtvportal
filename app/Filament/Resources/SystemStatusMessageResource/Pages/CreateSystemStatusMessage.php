<?php
namespace App\Filament\Resources\SystemStatusMessageResource\Pages;
use App\Filament\Resources\SystemStatusMessageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
class CreateSystemStatusMessage extends CreateRecord
{
    protected static string $resource = SystemStatusMessageResource::class;
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->requiresConfirmation()->modalDescription('Confirm the message, severity, platforms, and schedule before publishing this advisory.');
    }
}
