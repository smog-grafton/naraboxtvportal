<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommunicationLogResource\Pages;
use App\Models\CommunicationLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommunicationLogResource extends Resource
{
    protected static ?string $model = CommunicationLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';
    protected static ?string $navigationGroup = 'Engagement';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('recipient')->searchable(),
                Tables\Columns\TextColumn::make('subject')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('template_name')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('sent_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('failed_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunicationLogs::route('/'),
        ];
    }
}
