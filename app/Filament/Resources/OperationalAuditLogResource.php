<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OperationalAuditLogResource\Pages;
use App\Models\OperationalAuditLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OperationalAuditLogResource extends Resource
{
    protected static ?string $model = OperationalAuditLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?string $navigationLabel = 'Audit log';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('actor.name')->label('Admin')->default('System')->searchable(),
                Tables\Columns\TextColumn::make('event')->badge()->searchable(),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (string $state, OperationalAuditLog $record): string => class_basename($state).' #'.$record->auditable_id)
                    ->searchable(),
                Tables\Columns\TextColumn::make('ip_address')->label('IP')->toggleable(),
                Tables\Columns\TextColumn::make('new_values')
                    ->label('Changed values')
                    ->formatStateUsing(fn (?array $state): string => json_encode($state ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}')
                    ->wrap()
                    ->limit(180),
                Tables\Columns\TextColumn::make('old_values')
                    ->label('Previous values')
                    ->formatStateUsing(fn (?array $state): string => json_encode($state ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}')
                    ->wrap()
                    ->limit(180)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOperationalAuditLogs::route('/')];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
