<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorWithdrawalRequestResource\Pages;
use App\Models\CreatorWithdrawalRequest;
use App\Services\WithdrawalService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CreatorWithdrawalRequestResource extends Resource
{
    protected static ?string $model = CreatorWithdrawalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';
    protected static ?string $navigationLabel = 'Withdrawal Requests';
    protected static ?string $modelLabel = 'Withdrawal Request';
    protected static ?string $pluralModelLabel = 'Withdrawal Requests';
    protected static ?string $navigationGroup = 'Creator Finance';
    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Creator')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('UGX')
                    ->sortable(),
                Tables\Columns\TextColumn::make('method_display')
                    ->label('Method')
                    ->getStateUsing(fn ($record) => $record->payoutMethod
                        ? ($record->payoutMethod->method_type === 'mobile_money'
                            ? ($record->payoutMethod->masked_phone ?? 'Mobile Money')
                            : (($record->payoutMethod->bank_name ?? '') . ' ****' . substr($record->payoutMethod->account_number ?? '', -4)))
                        : '—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'under_review' => 'info',
                        'approved' => 'primary',
                        'processing' => 'gray',
                        'paid' => 'success',
                        'failed', 'rejected' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state)))
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference')
                    ->searchable(),
                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'under_review' => 'Under Review',
                        'approved' => 'Approved',
                        'processing' => 'Processing',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (CreatorWithdrawalRequest $record) => in_array(
                        $record->status,
                        [CreatorWithdrawalRequest::STATUS_PENDING, CreatorWithdrawalRequest::STATUS_UNDER_REVIEW]
                    ))
                    ->action(function (CreatorWithdrawalRequest $record): void {
                        app(WithdrawalService::class)->approve($record, auth()->user());
                        Notification::make()->title('Withdrawal approved.')->success()->send();
                    }),
                Tables\Actions\Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->visible(fn (CreatorWithdrawalRequest $record) => $record->status === CreatorWithdrawalRequest::STATUS_APPROVED)
                    ->action(function (CreatorWithdrawalRequest $record): void {
                        try {
                            app(WithdrawalService::class)->process($record);
                            Notification::make()->title('Withdrawal processed.')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Processing failed: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('admin_notes')
                            ->label('Rejection reason')
                            ->required(),
                    ])
                    ->visible(fn (CreatorWithdrawalRequest $record) => in_array(
                        $record->status,
                        [CreatorWithdrawalRequest::STATUS_PENDING, CreatorWithdrawalRequest::STATUS_UNDER_REVIEW, CreatorWithdrawalRequest::STATUS_APPROVED]
                    ))
                    ->action(function (CreatorWithdrawalRequest $record, array $data): void {
                        app(WithdrawalService::class)->reject($record, $data['admin_notes']);
                        Notification::make()->title('Withdrawal rejected.')->success()->send();
                    }),
                Tables\Actions\ViewAction::make()
                    ->url(fn (CreatorWithdrawalRequest $record) => Pages\ViewCreatorWithdrawal::getUrl(['record' => $record])),
            ])
            ->bulkActions([])
            ->defaultSort('requested_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreatorWithdrawalRequests::route('/'),
            'view' => Pages\ViewCreatorWithdrawal::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
