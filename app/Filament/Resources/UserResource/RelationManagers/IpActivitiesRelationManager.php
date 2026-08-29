<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class IpActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'ipActivities';

    protected static ?string $title = 'IP and device activity';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ip_address')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('activity_type')->badge()->searchable(),
                Tables\Columns\TextColumn::make('ip_address')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('device_id')->searchable()->copyable()->limit(24),
                Tables\Columns\TextColumn::make('user_agent')->limit(80)->wrap()->toggleable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('occurred_at', 'desc');
    }
}
