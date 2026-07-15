<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorEarningResource\Pages;
use App\Models\CreatorEarning;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CreatorEarningResource extends Resource
{
    protected static ?string $model = CreatorEarning::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Creator Earnings';
    protected static ?string $modelLabel = 'Creator Earning';
    protected static ?string $pluralModelLabel = 'Creator Earnings';
    protected static ?string $navigationGroup = 'Creator Finance';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Creator')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('earnable.title')
                    ->label('Title')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('transaction.transaction_ref')
                    ->label('Transaction Ref')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Gross')
                    ->money('UGX')
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_rate')
                    ->label('Commission %')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator_amount')
                    ->label('Creator Amount')
                    ->money('UGX')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'available' => 'success',
                        'withdrawn' => 'info',
                        'reversed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_at')
                    ->label('Available At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'available' => 'Available',
                        'withdrawn' => 'Withdrawn',
                        'reversed' => 'Reversed',
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
            'index' => Pages\ListCreatorEarnings::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
