<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorPayoutMethodResource\Pages;
use App\Models\CreatorPayoutMethod;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CreatorPayoutMethodResource extends Resource
{
    protected static ?string $model = CreatorPayoutMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Payout Methods';
    protected static ?string $modelLabel = 'Payout Method';
    protected static ?string $pluralModelLabel = 'Payout Methods';
    protected static ?string $navigationGroup = 'Creator Finance';
    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Creator')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('method_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'mobile_money' ? 'success' : 'info')
                    ->formatStateUsing(fn (string $state): string => $state === 'mobile_money' ? 'Mobile Money' : 'Bank'),
                Tables\Columns\TextColumn::make('provider')
                    ->label('Provider'),
                Tables\Columns\TextColumn::make('phone_number')
                    ->label('Phone / Account')
                    ->formatStateUsing(fn ($record) => $record->method_type === 'mobile_money'
                        ? ($record->masked_phone ?? '—')
                        : ($record->masked_account ?? '—')),
                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('method_type')
                    ->options([
                        'mobile_money' => 'Mobile Money',
                        'bank' => 'Bank',
                    ]),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreatorPayoutMethods::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
