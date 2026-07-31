<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorWalletResource\Pages;
use App\Filament\Resources\CreatorWalletResource\RelationManagers\EntriesRelationManager;
use App\Models\CreatorWallet;
use App\Services\CreatorAuditService;
use App\Services\CreatorWalletService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CreatorWalletResource extends Resource
{
    protected static ?string $model = CreatorWallet::class;

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static ?string $navigationLabel = 'Creator Wallets';

    protected static ?string $navigationGroup = 'Creator Finance';

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Creator wallet')
                ->schema([
                    Infolists\Components\TextEntry::make('user.name')->label('Creator'),
                    Infolists\Components\TextEntry::make('user.email')->label('Email'),
                    Infolists\Components\TextEntry::make('currency')->badge(),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('hold_reason')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('held_at')->dateTime(),
                    Infolists\Components\TextEntry::make('holder.name')->label('Held by'),
                ])->columns(2),
            Infolists\Components\Section::make('Ledger balances')
                ->schema([
                    Infolists\Components\TextEntry::make('pending_balance')
                        ->getStateUsing(fn (CreatorWallet $record) => static::balance($record, 'pending'))
                        ->money('UGX', divideBy: 1),
                    Infolists\Components\TextEntry::make('available_balance')
                        ->getStateUsing(fn (CreatorWallet $record) => static::balance($record, 'available'))
                        ->money('UGX', divideBy: 1),
                    Infolists\Components\TextEntry::make('on_hold_balance')
                        ->getStateUsing(fn (CreatorWallet $record) => static::balance($record, 'on_hold'))
                        ->money('UGX', divideBy: 1),
                    Infolists\Components\TextEntry::make('reserved_balance')
                        ->getStateUsing(fn (CreatorWallet $record) => static::balance($record, 'reserved'))
                        ->money('UGX', divideBy: 1),
                    Infolists\Components\TextEntry::make('paid_balance')
                        ->getStateUsing(fn (CreatorWallet $record) => static::balance($record, 'paid'))
                        ->money('UGX', divideBy: 1),
                    Infolists\Components\TextEntry::make('reversed_balance')
                        ->getStateUsing(fn (CreatorWallet $record) => static::balance($record, 'reversed'))
                        ->money('UGX', divideBy: 1),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Creator')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('currency')->badge(),
                Tables\Columns\TextColumn::make('available_balance')
                    ->label('Available')
                    ->getStateUsing(fn (CreatorWallet $record) => static::balance($record, 'available'))
                    ->money('UGX', divideBy: 1),
                Tables\Columns\TextColumn::make('pending_balance')
                    ->label('Pending')
                    ->getStateUsing(fn (CreatorWallet $record) => static::balance($record, 'pending'))
                    ->money('UGX', divideBy: 1),
                Tables\Columns\TextColumn::make('reserved_balance')
                    ->label('Reserved')
                    ->getStateUsing(fn (CreatorWallet $record) => static::balance($record, 'reserved'))
                    ->money('UGX', divideBy: 1),
                Tables\Columns\TextColumn::make('on_hold_balance')
                    ->label('On hold')
                    ->getStateUsing(fn (CreatorWallet $record) => static::balance($record, 'on_hold'))
                    ->money('UGX', divideBy: 1),
                Tables\Columns\TextColumn::make('paid_balance')
                    ->label('Paid')
                    ->getStateUsing(fn (CreatorWallet $record) => static::balance($record, 'paid'))
                    ->money('UGX', divideBy: 1)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reversed_balance')
                    ->label('Reversed')
                    ->getStateUsing(fn (CreatorWallet $record) => static::balance($record, 'reversed'))
                    ->money('UGX', divideBy: 1)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'held' => 'Held',
                    'closed' => 'Closed',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('hold')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->visible(fn (CreatorWallet $record) => $record->status !== 'held')
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(function (CreatorWallet $record, array $data): void {
                        $record->update([
                            'status' => 'held',
                            'hold_reason' => $data['reason'],
                            'held_at' => now(),
                            'held_by' => auth()->id(),
                        ]);
                        app(CreatorAuditService::class)->record(
                            'creator.wallet_held',
                            auth()->user(),
                            $record->user,
                            $record,
                            [],
                            ['reason' => $data['reason']]
                        );
                    }),
                Tables\Actions\Action::make('release_hold')
                    ->label('Release hold')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CreatorWallet $record) => $record->status === 'held')
                    ->action(function (CreatorWallet $record): void {
                        $reason = $record->hold_reason;
                        $record->update([
                            'status' => 'active',
                            'hold_reason' => null,
                            'held_at' => null,
                            'held_by' => null,
                        ]);
                        app(CreatorAuditService::class)->record(
                            'creator.wallet_hold_released',
                            auth()->user(),
                            $record->user,
                            $record,
                            ['reason' => $reason],
                            []
                        );
                    }),
                Tables\Actions\Action::make('adjust')
                    ->label('Audited adjustment')
                    ->visible(fn (CreatorWallet $record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Select::make('direction')
                            ->options(['credit' => 'Credit', 'debit' => 'Debit'])
                            ->required(),
                        Forms\Components\TextInput::make('amount_minor')
                            ->label('Whole UGX')
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(function (CreatorWallet $record, array $data): void {
                        $amount = (int) $data['amount_minor'] * ($data['direction'] === 'debit' ? -1 : 1);
                        $entry = app(CreatorWalletService::class)->post(
                            $record->user,
                            $amount,
                            $data['direction'] === 'debit' ? 'manual_debit' : 'manual_credit',
                            'available',
                            'manual-adjustment:'.Str::uuid(),
                            CreatorWallet::class,
                            $record->id,
                            $data['reason'],
                            ['reason' => $data['reason']],
                            auth()->user()
                        );
                        app(CreatorAuditService::class)->record(
                            'creator.wallet_manual_adjustment',
                            auth()->user(),
                            $record->user,
                            $entry,
                            [],
                            ['amount_minor' => $amount, 'reason' => $data['reason']]
                        );
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [EntriesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreatorWallets::route('/'),
            'view' => Pages\ViewCreatorWallet::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    private static function balance(CreatorWallet $wallet, string $bucket): int
    {
        return (int) $wallet->entries()
            ->where('bucket', $bucket)
            ->where('status', 'posted')
            ->sum('amount_minor');
    }
}
