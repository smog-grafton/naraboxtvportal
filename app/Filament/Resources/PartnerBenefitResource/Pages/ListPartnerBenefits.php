<?php

namespace App\Filament\Resources\PartnerBenefitResource\Pages;

use App\Filament\Resources\PartnerBenefitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartnerBenefits extends ListRecords
{
    protected static string $resource = PartnerBenefitResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
