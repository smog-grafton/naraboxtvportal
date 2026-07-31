<?php

namespace App\Filament\Resources\VJClaimRequestResource\Pages;

use App\Filament\Resources\CreatorApplicationResource;
use App\Filament\Resources\VJClaimRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVJClaimRequest extends ViewRecord
{
    protected static string $resource = VJClaimRequestResource::class;

    protected function getHeaderActions(): array
    {
        $application = $this->record->user?->creatorApplication;

        return [
            Actions\Action::make('secure_application')
                ->label('Open secure creator application')
                ->icon('heroicon-o-shield-check')
                ->url($application ? CreatorApplicationResource::getUrl('view', ['record' => $application]) : null)
                ->disabled(! $application),
        ];
    }
}
