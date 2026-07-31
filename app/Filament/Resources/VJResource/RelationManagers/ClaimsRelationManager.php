<?php

namespace App\Filament\Resources\VJResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ClaimsRelationManager extends RelationManager
{
    protected static string $relationship = 'claims';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.name')->label('Claimant')->searchable(),
            Tables\Columns\TextColumn::make('relationship_explanation')->limit(100)->wrap(),
            Tables\Columns\TextColumn::make('verification_method')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('submitted_at')->dateTime(),
            Tables\Columns\TextColumn::make('reviewed_at')->dateTime(),
        ])->headerActions([])->actions([]);
    }
}
