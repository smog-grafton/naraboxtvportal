<?php

namespace App\Filament\Resources\PartnerResource\Pages;

use App\Filament\Resources\PartnerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePartner extends CreateRecord
{
    protected static string $resource = PartnerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Str::slug($data['slug'] ?? $data['display_name']);
        $data['referral_code'] = strtoupper(trim((string) ($data['referral_code'] ?? Str::upper(Str::slug($data['slug'], '')))));

        return $data;
    }
}
