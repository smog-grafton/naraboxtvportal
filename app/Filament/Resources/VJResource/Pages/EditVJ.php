<?php

namespace App\Filament\Resources\VJResource\Pages;

use App\Filament\Resources\VJResource;
use App\Models\PushNotification;
use App\Services\PushNotificationService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVJ extends EditRecord
{
    protected static string $resource = VJResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $vj = $this->record;
        $formState = $this->form->getState();

        if (!empty($formState['send_push_on_save'])) {
            $title = $formState['push_title'] ?: $vj->name;
            $body = $formState['push_body'] ?: 'Updated VJ: ' . $vj->name;

            $notification = PushNotification::create([
                'title' => $title,
                'body' => $body,
                'image_url' => $formState['push_image_url'] ?? null,
                'deep_link' => 'app://vj/' . $vj->id,
                'target_platform' => $formState['push_target_platform'] ?? 'all',
                'target_audience' => 'all',
                'provider' => 'default',
                'notification_type' => 'marketing',
                'status' => 'queued',
            ]);

            PushNotificationService::send($notification);
        }
    }
}
