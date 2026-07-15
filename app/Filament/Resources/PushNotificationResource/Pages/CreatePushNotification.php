<?php

namespace App\Filament\Resources\PushNotificationResource\Pages;

use App\Filament\Resources\PushNotificationResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePushNotification extends CreateRecord
{
    protected static string $resource = PushNotificationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PushNotificationResource::mutatePushNotificationData($data);
    }
}
