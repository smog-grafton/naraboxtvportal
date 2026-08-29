<?php

namespace App\Filament\Resources\SecurityRuleResource\Pages;

use App\Filament\Resources\SecurityRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSecurityRule extends EditRecord
{
    protected static string $resource = SecurityRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()->requiresConfirmation()];
    }
}
