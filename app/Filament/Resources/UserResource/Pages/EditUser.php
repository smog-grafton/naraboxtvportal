<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\MediaLibrary;
use App\Models\VJ;
use App\Services\AccountSecurityService;
use App\Services\SecurityEventService;
use Filament\Actions;
use Filament\Forms;
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
            Actions\Action::make('restrict_payments')
                ->label('Restrict Payments')->icon('heroicon-o-no-symbol')->color('warning')->requiresConfirmation()
                ->visible(fn () => $user->account_status === 'ACTIVE' && ! $user->isAdmin())
                ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes')])
                ->action(function (array $data) use ($user): void {
                    app(AccountSecurityService::class)->restrictPayments($user, $data['reason'], $data['notes'] ?? null, 'MANUAL', auth()->user());
                    Notification::make()->title('Payments restricted')->warning()->send();
                    $this->refreshFormData(['account_status', 'risk_level', 'security_reason', 'security_notes']);
                }),
            Actions\Action::make('suspend_user')
                ->label('Suspend')->icon('heroicon-o-pause-circle')->color('danger')->requiresConfirmation()
                ->visible(fn () => ! in_array($user->account_status, ['SUSPENDED', 'BANNED'], true)
                    && UserResource::canSuspendOrBan($user))
                ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes'), Forms\Components\DateTimePicker::make('expires_at')->required()->minDate(now())])
                ->action(function (array $data) use ($user): void {
                    app(AccountSecurityService::class)->suspend($user, $data['reason'], $data['notes'] ?? null, $data['expires_at'], auth()->user());
                    Notification::make()->title('User suspended')->warning()->send();
                }),
            Actions\Action::make('ban_user')
                ->label('Ban')->icon('heroicon-o-shield-exclamation')->color('danger')->requiresConfirmation()
                ->visible(fn () => $user->account_status !== 'BANNED'
                    && UserResource::canSuspendOrBan($user))
                ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes'), Forms\Components\DateTimePicker::make('expires_at')->helperText('Blank means permanent.')])
                ->action(function (array $data) use ($user): void {
                    app(AccountSecurityService::class)->ban($user, $data['reason'], $data['notes'] ?? null, $data['expires_at'] ?? null, auth()->user());
                    Notification::make()->title('User banned')->danger()->send();
                }),
            Actions\Action::make('restore_active')
                ->label('Restore Active')->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()
                ->visible(fn () => $user->account_status !== 'ACTIVE')
                ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes')])
                ->action(function (array $data) use ($user): void {
                    app(AccountSecurityService::class)->activate($user, $data['reason'], $data['notes'] ?? null, auth()->user());
                    Notification::make()->title('Account restored')->success()->send();
                }),
            Actions\Action::make('force_logout')
                ->label('Force Logout')->icon('heroicon-o-arrow-right-start-on-rectangle')->requiresConfirmation()
                ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes')])
                ->action(function (array $data) use ($user): void {
                    app(AccountSecurityService::class)->revokeSessions($user);
                    app(SecurityEventService::class)->record('FORCE_LOGOUT', ['user_id' => $user->id, 'risk_level' => 'HIGH', 'reason' => $data['reason'], 'actor_user_id' => auth()->id(), 'metadata' => ['notes' => $data['notes'] ?? null]]);
                    Notification::make()->title('Sessions and tokens revoked')->success()->send();
                }),
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
        ];
    }
}
