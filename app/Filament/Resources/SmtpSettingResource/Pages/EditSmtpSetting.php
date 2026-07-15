<?php

namespace App\Filament\Resources\SmtpSettingResource\Pages;

use App\Filament\Resources\SmtpSettingResource;
use App\Models\SmtpSetting;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EditSmtpSetting extends EditRecord
{
    protected static string $resource = SmtpSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('testSmtp')
                ->label('Test SMTP')
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    TextInput::make('email')
                        ->label('Send test to')
                        ->email()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->record->applyToConfig();

                        $mailManager = app('mail.manager');
                        if (method_exists($mailManager, 'purge')) {
                            $mailManager->purge('smtp');
                        }

                        Mail::mailer('smtp')->raw(
                            "This is a direct SMTP test from Narabox TV.\n\nHost: {$this->record->host}\nPort: {$this->record->port}\nEncryption: {$this->record->encryption}\nTime: " . now()->toDateTimeString(),
                            function ($message) use ($data): void {
                                $message
                                    ->to($data['email'])
                                    ->subject('Narabox TV SMTP Direct Test');
                            }
                        );

                        Notification::make()
                            ->title('SMTP test email sent successfully')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('SMTP test failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If this setting is being activated, deactivate all others
        if ($data['is_active'] ?? false) {
            SmtpSetting::where('id', '!=', $this->record->id)
                ->update(['is_active' => false]);
        }

        return $data;
    }
}
