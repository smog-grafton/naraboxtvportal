<?php
namespace App\Filament\Resources\PlatformVersionPolicyResource\Pages;
use App\Filament\Resources\PlatformVersionPolicyResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
class EditPlatformVersionPolicy extends EditRecord
{
    protected static string $resource = PlatformVersionPolicyResource::class;
    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->requiresConfirmation()->modalHeading('Apply version policy?')->modalDescription('Raising the minimum build can block installed apps. Confirm build numbers and store destination before saving.');
    }
}
