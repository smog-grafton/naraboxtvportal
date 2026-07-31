<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorPayoutAccountResource\Pages;
use App\Models\CreatorPayoutMethod;
use App\Services\CreatorAuditService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CreatorPayoutAccountResource extends Resource
{
    protected static ?string $model = CreatorPayoutMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Payout Accounts';

    protected static ?string $modelLabel = 'Creator Payout Account';

    protected static ?string $pluralModelLabel = 'Creator Payout Accounts';

    protected static ?string $navigationGroup = 'Creator Finance';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->description('Encrypted creator-owned destinations. Full phone and bank account values are never displayed in Filament.')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Creator')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.email')->label('Email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('method_type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('provider')->label('Provider')->badge(),
                Tables\Columns\TextColumn::make('masked_destination')
                    ->label('Destination')
                    ->getStateUsing(fn (CreatorPayoutMethod $record) => $record->method_type === 'mobile_money'
                        ? ($record->masked_phone ?? '—')
                        : ($record->masked_account ?? '—')),
                Tables\Columns\TextColumn::make('verification_status')->label('Verification')->badge(),
                Tables\Columns\IconColumn::make('is_default')->label('Default')->boolean(),
                Tables\Columns\TextColumn::make('withdrawal_hold_until')->label('Hold until')->dateTime(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('method_type')->options([
                    'mobile_money' => 'Mobile money',
                    'bank' => 'Bank transfer',
                ]),
                Tables\Filters\SelectFilter::make('verification_status')->options([
                    'pending' => 'Pending',
                    'verified' => 'Verified',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CreatorPayoutMethod $record) => ! $record->is_verified)
                    ->action(function (CreatorPayoutMethod $record): void {
                        $record->update([
                            'is_verified' => true,
                            'verification_status' => 'verified',
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                        ]);
                        app(CreatorAuditService::class)->record(
                            'creator.payout_account_verified',
                            auth()->user(),
                            $record->user,
                            $record
                        );
                    }),
                Tables\Actions\Action::make('reject')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (CreatorPayoutMethod $record) => $record->verification_status === 'pending')
                    ->action(function (CreatorPayoutMethod $record): void {
                        $record->update([
                            'is_verified' => false,
                            'verification_status' => 'rejected',
                        ]);
                        app(CreatorAuditService::class)->record(
                            'creator.payout_account_rejected',
                            auth()->user(),
                            $record->user,
                            $record
                        );
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCreatorPayoutAccounts::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
