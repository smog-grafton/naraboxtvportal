<?php

namespace App\Filament\Resources\VJResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TVShowsRelationManager extends RelationManager
{
    protected static string $relationship = 'tvShows';

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable(),
            Tables\Columns\TextColumn::make('submission_origin')->badge(),
            Tables\Columns\TextColumn::make('submittingUser.name')->label('Submitted by')->placeholder('Platform managed'),
            Tables\Columns\TextColumn::make('processing_status')->badge(),
            Tables\Columns\TextColumn::make('editorial_status')->badge(),
            Tables\Columns\TextColumn::make('publication_status')->badge(),
            Tables\Columns\TextColumn::make('seasons_count')->counts('seasons')->label('Seasons'),
        ])->headerActions([])->actions([]);
    }
}
