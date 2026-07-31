<?php

namespace App\Filament\Resources\FinancialSettingResource\Pages;

use App\Filament\Resources\FinancialSettingResource;
use App\Models\FinancialSetting;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinancialSettings extends ListRecords
{
    protected static string $resource = FinancialSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => FinancialSetting::query()->doesntExist()),
        ];
    }
}
