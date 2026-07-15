<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Models\MediaLibrary;
use App\Models\Role;
use App\Models\VJ;
use App\Filament\Resources\UserResource;
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
        $roleVj = Role::where('name', 'vj')->first();
        $roleMediaLibrary = Role::where('name', 'media_library')->first();

        return [
            Actions\Action::make('make_vj')
                ->label('Make VJ')
                ->icon('heroicon-o-microphone')
                ->color('warning')
                ->visible(fn () => $roleVj && $user->role_id !== $roleVj->id)
                ->requiresConfirmation()
                ->modalDescription('Assign this user as a Video Jockey. A VJ profile will be created if one does not exist.')
                ->action(function () use ($user, $roleVj) {
                    if (!$roleVj) return;
                    $vj = VJ::where('user_id', $user->id)->first();
                    if (!$vj) {
                        VJ::create([
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'slug' => Str::slug($user->name),
                            'is_active' => true,
                            'is_verified' => false,
                        ]);
                    }
                    $user->update(['role_id' => $roleVj->id]);
                    Notification::make()->title('User is now a VJ')->success()->send();
                    $this->refreshFormData(['role_id']);
                }),
            Actions\Action::make('make_media_library')
                ->label('Make Media Library')
                ->icon('heroicon-o-folder')
                ->color('success')
                ->visible(fn () => $roleMediaLibrary && $user->role_id !== $roleMediaLibrary->id)
                ->requiresConfirmation()
                ->modalDescription('Assign this user as a Media Library (studio). A Media Library profile will be created if one does not exist.')
                ->action(function () use ($user, $roleMediaLibrary) {
                    if (!$roleMediaLibrary) return;
                    $library = MediaLibrary::where('user_id', $user->id)->first();
                    if (!$library) {
                        MediaLibrary::create([
                            'user_id' => $user->id,
                            'name' => $user->name,
                            'slug' => Str::slug($user->name),
                            'is_active' => true,
                            'is_verified' => true,
                        ]);
                    }
                    $user->update(['role_id' => $roleMediaLibrary->id]);
                    Notification::make()->title('User is now a Media Library')->success()->send();
                    $this->refreshFormData(['role_id']);
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
