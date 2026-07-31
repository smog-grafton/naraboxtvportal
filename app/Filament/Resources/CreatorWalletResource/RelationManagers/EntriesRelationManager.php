<?php

namespace App\Filament\Resources\CreatorWalletResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('entry_type')->badge(),
                Tables\Columns\TextColumn::make('bucket')->badge(),
                Tables\Columns\TextColumn::make('amount_minor')->money('UGX', divideBy: 1),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('description')->wrap()->limit(100),
                Tables\Columns\TextColumn::make('reference_type')->formatStateUsing(fn ($state) => $state ? class_basename($state) : '—'),
                Tables\Columns\TextColumn::make('reference_id'),
                Tables\Columns\TextColumn::make('created_by')->label('Admin ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bucket')->options([
                    'pending' => 'Pending',
                    'available' => 'Available',
                    'on_hold' => 'On hold',
                    'reserved' => 'Reserved',
                    'paid' => 'Paid',
                    'reversed' => 'Reversed',
                ]),
                Tables\Filters\SelectFilter::make('entry_type')
                    ->options(fn () => $this->getOwnerRecord()->entries()
                        ->distinct()
                        ->orderBy('entry_type')
                        ->pluck('entry_type', 'entry_type')
                        ->all()),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->actions([]);
    }
}
