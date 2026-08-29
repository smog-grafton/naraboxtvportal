<?php
namespace App\Filament\Resources\MaintenanceWindowResource\Pages;
use App\Filament\Resources\MaintenanceWindowResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
class EditMaintenanceWindow extends EditRecord
{
    protected static string $resource = MaintenanceWindowResource::class;
    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->requiresConfirmation()->modalHeading('Apply maintenance changes?')->modalDescription('This creates an audited revision. Confirm platform scope, feature isolation, and schedule before saving.');
    }
}
