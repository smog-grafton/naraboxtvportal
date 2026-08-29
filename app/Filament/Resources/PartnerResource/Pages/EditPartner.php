<?php

namespace App\Filament\Resources\PartnerResource\Pages;

use App\Filament\Resources\PartnerResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditPartner extends EditRecord
{
    protected static string $resource = PartnerResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['slug'] = Str::slug($data['slug'] ?? $this->record->slug);
        $data['referral_code'] = strtoupper(trim((string) $data['referral_code']));

        return $data;
    }
}
