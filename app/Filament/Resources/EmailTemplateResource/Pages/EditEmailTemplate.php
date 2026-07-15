<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use App\Services\CommunicationService;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sendTest')
                ->label('Send test')
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    TextInput::make('email')->email()->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $log = app(CommunicationService::class)->deliverTemplatedEmail(
                            to: $data['email'],
                            templateName: $this->record->name,
                            data: [
                                'user_name' => 'Narabox Tester',
                                'email' => $data['email'],
                                'movie_title' => 'The Frontline',
                                'show_title' => 'Night Watch',
                                'episode_title' => 'Pilot',
                                'subscription_plan' => 'Monthly Plan',
                                'amount' => '10,000',
                                'status' => 'TEST',
                                'watch_url' => rtrim((string) config('app.url'), '/') . '/',
                                'unsubscribe_url' => rtrim((string) config('app.url'), '/') . '/api/v1/notifications/preferences/unsubscribe',
                                'created_at' => now(),
                                'expiry_date' => now()->addMonth(),
                                'title' => 'Admin test',
                                'message' => 'This is a test alert from Narabox TV.',
                            ],
                        );

                        if ($log->status === 'sent') {
                            Notification::make()->title('Test email sent successfully')->success()->send();

                            return;
                        }

                        Notification::make()
                            ->title('Test email failed')
                            ->body($log->error_message ?: 'SMTP send failed.')
                            ->danger()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Test email failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
