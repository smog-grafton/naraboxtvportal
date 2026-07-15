<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VJClaimRequestResource\Pages;
use App\Models\VJClaimRequest;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VJClaimRequestResource extends Resource
{
    protected static ?string $model = VJClaimRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $navigationLabel = 'VJ Claims';
    protected static ?string $modelLabel = 'VJ Claim Request';
    protected static ?string $pluralModelLabel = 'VJ Claim Requests';
    protected static ?string $navigationGroup = 'Creator Management';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vj.name')
                    ->label('VJ')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (VJClaimRequest $record) => $record->status === 'pending')
                    ->action(function (VJClaimRequest $record) {
                        $vj = $record->vj;
                        if ($vj->user_id) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Already claimed')
                                ->body('This VJ is already linked to another user.')
                                ->send();
                            return;
                        }
                        $vj->update(['user_id' => $record->user_id]);
                        $record->update([
                            'status' => 'approved',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'rejection_reason' => null,
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Claim approved')
                            ->body("{$record->user->name} is now linked to {$vj->name}.")
                            ->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason (optional)')
                            ->rows(3),
                    ])
                    ->visible(fn (VJClaimRequest $record) => $record->status === 'pending')
                    ->action(function (VJClaimRequest $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'rejection_reason' => $data['rejection_reason'] ?? null,
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title('Claim rejected')
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVJClaimRequests::route('/'),
        ];
    }
}
