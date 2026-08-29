<?php

namespace App\Filament\Resources\PartnerBenefitTypeResource\Pages;

use App\Filament\Resources\PartnerBenefitTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartnerBenefitTypes extends ListRecords
{
    protected static string $resource = PartnerBenefitTypeResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
