<?php
namespace App\Filament\Resources\PlatformVersionPolicyResource\Pages;
use App\Filament\Resources\PlatformVersionPolicyResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
class CreatePlatformVersionPolicy extends CreateRecord
{
    protected static string $resource = PlatformVersionPolicyResource::class;
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->requiresConfirmation()->modalDescription('Verify the platform, minimum build, latest build, effective time, and store URL. Unsupported builds can be blocked immediately.');
    }
}
