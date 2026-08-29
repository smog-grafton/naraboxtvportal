<?php

namespace App\Filament\Resources\PartnerBenefitResource\Pages;

use App\Filament\Resources\PartnerBenefitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartnerBenefit extends EditRecord
{
    protected static string $resource = PartnerBenefitResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
