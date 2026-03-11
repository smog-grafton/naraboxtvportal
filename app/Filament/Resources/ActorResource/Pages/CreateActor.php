<?php

namespace App\Filament\Resources\ActorResource\Pages;

use App\Filament\Resources\ActorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateActor extends CreateRecord
{
    protected static string $resource = ActorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Handle image: use URL if provided, otherwise use uploaded file
        if (isset($data['image_url']) && !empty($data['image_url'])) {
            $data['image'] = $data['image_url'];
            unset($data['image_url']);
        } elseif (empty($data['image'])) {
            $data['image'] = null;
        }

        return $data;
    }
}
