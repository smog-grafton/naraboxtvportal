<?php

namespace App\Filament\Resources\CreatorSettlementResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Creator')->searchable(),
                Tables\Columns\TextColumn::make('content_type')->formatStateUsing(fn ($state) => class_basename($state)),
                Tables\Columns\TextColumn::make('content_id'),
                Tables\Columns\TextColumn::make('qualified_metric')->label('Qualified seconds')->numeric(),
                Tables\Columns\TextColumn::make('amount_minor')->money('UGX', divideBy: 1),
                Tables\Columns\TextColumn::make('eligibility_status')->badge(),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
