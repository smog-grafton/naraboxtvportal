<?php

namespace App\Filament\Resources\CreatorWithdrawalRequestResource\Pages;

use App\Filament\Resources\CreatorWithdrawalRequestResource;
use App\Models\CreatorWithdrawalRequest;
use App\Services\WithdrawalService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;

class ViewCreatorWithdrawal extends ViewRecord
{
    protected static string $resource = CreatorWithdrawalRequestResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->record;
        $actions = [];

        if (in_array($record->status, [
            CreatorWithdrawalRequest::STATUS_PENDING,
            CreatorWithdrawalRequest::STATUS_UNDER_REVIEW,
        ])) {
            $actions[] = Actions\Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->action(fn () => $this->approve());
        }

        if ($record->status === CreatorWithdrawalRequest::STATUS_APPROVED) {
            $actions[] = Actions\Action::make('process')
                ->label('Process')
                ->color('primary')
                ->action(fn () => $this->process());
        }

        if (in_array($record->status, [
            CreatorWithdrawalRequest::STATUS_PENDING,
            CreatorWithdrawalRequest::STATUS_UNDER_REVIEW,
            CreatorWithdrawalRequest::STATUS_APPROVED,
        ])) {
            $actions[] = Actions\Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\Textarea::make('admin_notes')
                        ->label('Rejection reason')
                        ->required(),
                ])
                ->action(fn (array $data) => $this->reject($data));
        }

        return $actions;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Withdrawal Details')
                    ->schema([
                        TextEntry::make('user.name')->label('Creator'),
                        TextEntry::make('amount')->label('Amount')->money('UGX'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('reference')->label('Reference'),
                        TextEntry::make('requested_at')->dateTime()->label('Requested At'),
                        TextEntry::make('processed_at')->dateTime()->label('Processed At'),
                        TextEntry::make('failure_reason')->label('Failure Reason')->visible(fn ($record) => !empty($record->failure_reason)),
                        TextEntry::make('admin_notes')->label('Admin Notes')->visible(fn ($record) => !empty($record->admin_notes)),
                    ])->columns(2),
                Section::make('Payout Attempts')
                    ->schema([
                        RepeatableEntry::make('payoutAttempts')
                            ->schema([
                                TextEntry::make('gateway')->label('Gateway'),
                                TextEntry::make('status')->label('Status'),
                                TextEntry::make('external_id')->label('External ID'),
                                TextEntry::make('attempted_at')->dateTime()->label('Attempted At'),
                                TextEntry::make('notes')->label('Notes'),
                            ])
                            ->columns(4),
                    ])
                    ->visible(fn ($record) => $record->payoutAttempts->isNotEmpty()),
            ]);
    }

    private function approve(): void
    {
        app(WithdrawalService::class)->approve($this->record, auth()->user());
        Notification::make()->title('Withdrawal approved.')->success()->send();
        $this->record->refresh();
    }

    private function process(): void
    {
        try {
            app(WithdrawalService::class)->process($this->record);
            Notification::make()->title('Withdrawal processed.')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Processing failed: ' . $e->getMessage())->danger()->send();
        }
        $this->record->refresh();
    }

    private function reject(array $data): void
    {
        app(WithdrawalService::class)->reject($this->record, $data['admin_notes']);
        Notification::make()->title('Withdrawal rejected.')->success()->send();
        $this->record->refresh();
    }
}
