<?php

namespace App\Filament\Resources\PartnerBenefitTypeResource\Pages;

use App\Filament\Resources\PartnerBenefitTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartnerBenefitType extends EditRecord
{
    protected static string $resource = PartnerBenefitTypeResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
