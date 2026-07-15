<?php

namespace App\Filament\Resources\CreatorApplicationResource\Pages;

use App\Filament\Resources\CreatorApplicationResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCreatorApplication extends EditRecord
{
    protected static string $resource = CreatorApplicationResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\Action::make('sync_vj_profile')
                ->label('Sync VJ/Profile')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'approved')
                ->requiresConfirmation()
                ->modalDescription('Ensure VJ or Media Library profile exists for this approved applicant. Use this if the applicant was approved via Edit and the profile was never created.')
                ->action(function () {
                    CreatorApplicationResource::approveApplication($this->record);
                    Notification::make()
                        ->title('Profile synced')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];

        return $actions;
    }
}
