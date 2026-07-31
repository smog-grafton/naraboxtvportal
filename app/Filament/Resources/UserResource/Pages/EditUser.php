<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\MediaLibrary;
use App\Models\VJ;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $user = $this->record;

        return [
            Actions\Action::make('make_vj')
                ->label('Add VJ profile')
                ->icon('heroicon-o-microphone')
                ->color('warning')
                ->visible(fn () => ! $user->vjProfile()->exists())
                ->requiresConfirmation()
                ->modalDescription('Create a VJ profile for this account without changing its administrator or customer access.')
                ->action(function () use ($user) {
                    $vj = VJ::where('user_id', $user->id)->first();
                    if (! $vj) {
                        VJ::create([
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'slug' => Str::slug($user->name),
                            'is_active' => true,
                            'is_verified' => false,
                        ]);
                    }
                    Notification::make()->title('VJ profile added')->success()->send();
                }),
            Actions\Action::make('make_media_library')
                ->label('Add media library profile')
                ->icon('heroicon-o-folder')
                ->color('success')
                ->visible(fn () => ! $user->mediaLibraryProfile()->exists())
                ->requiresConfirmation()
                ->modalDescription('Create a media library profile for this account without changing its administrator or customer access.')
                ->action(function () use ($user) {
                    $library = MediaLibrary::where('user_id', $user->id)->first();
                    if (! $library) {
                        MediaLibrary::create([
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'slug' => Str::slug($user->name),
                            'is_active' => true,
                            'is_verified' => true,
                        ]);
                    }
                    Notification::make()->title('Media library profile added')->success()->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
