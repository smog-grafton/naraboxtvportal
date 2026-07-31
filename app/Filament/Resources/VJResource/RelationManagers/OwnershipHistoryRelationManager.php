<?php

namespace App\Filament\Resources\VJResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OwnershipHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'ownershipHistory';

    protected static ?string $title = 'Account ownership history';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('action')->badge(),
                Tables\Columns\TextColumn::make('previousUser.name')->label('Previous account')->placeholder('None'),
                Tables\Columns\TextColumn::make('newUser.name')->label('New account')->placeholder('Platform managed'),
                Tables\Columns\TextColumn::make('actor.name')->label('Approved by')->placeholder('System'),
                Tables\Columns\TextColumn::make('reason')->wrap()->limit(120),
                Tables\Columns\TextColumn::make('occurred_at')->dateTime()->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->defaultSort('occurred_at', 'desc');
    }
}
