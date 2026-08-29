<?php
namespace App\Filament\Resources\MaintenanceWindowResource\Pages;
use App\Filament\Resources\MaintenanceWindowResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
class CreateMaintenanceWindow extends CreateRecord
{
    protected static string $resource = MaintenanceWindowResource::class;
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->requiresConfirmation()->modalHeading('Confirm maintenance scope')->modalDescription('Verify affected platforms, disabled features, start time, and end time. Active full maintenance can immediately block production clients.');
    }
}
