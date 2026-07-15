<?php

namespace App\Filament\Resources\PushNotificationResource\Pages;

use App\Filament\Resources\PushNotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPushNotification extends EditRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return PushNotificationResource::fillDestinationData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PushNotificationResource::mutatePushNotificationData($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
