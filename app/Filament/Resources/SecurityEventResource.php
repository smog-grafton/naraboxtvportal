<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SecurityEventResource\Pages;
use App\Models\SecurityEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SecurityEventResource extends Resource
{
    protected static ?string $model = SecurityEvent::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Security';
    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Event')->schema([
                Forms\Components\TextInput::make('event_type')->disabled(),
                Forms\Components\TextInput::make('risk_level')->disabled(),
                Forms\Components\TextInput::make('user_id')->disabled(),
                Forms\Components\TextInput::make('actor_user_id')->disabled(),
                Forms\Components\TextInput::make('ip_address')->disabled(),
                Forms\Components\TextInput::make('device_id')->disabled(),
                Forms\Components\TextInput::make('payer_phone')->disabled(),
                Forms\Components\TextInput::make('route')->disabled(),
                Forms\Components\Textarea::make('reason')->disabled()->columnSpanFull(),
                Forms\Components\Textarea::make('metadata')->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))->disabled()->columnSpanFull()->rows(12),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('occurred_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('event_type')->badge()->searchable(),
            Tables\Columns\TextColumn::make('risk_level')->badge()->color(fn (string $state) => match ($state) { 'CRITICAL' => 'danger', 'HIGH' => 'warning', 'ELEVATED' => 'info', default => 'gray' }),
            Tables\Columns\TextColumn::make('user.email')->label('User')->searchable(),
            Tables\Columns\TextColumn::make('ip_address')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('device_id')->searchable()->limit(18)->copyable(),
            Tables\Columns\TextColumn::make('payer_phone')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('reason')->limit(60)->wrap(),
        ])->filters([
            Tables\Filters\SelectFilter::make('risk_level')->options(array_combine(['NORMAL', 'WATCH', 'ELEVATED', 'HIGH', 'CRITICAL'], ['Normal', 'Watch', 'Elevated', 'High', 'Critical'])),
            Tables\Filters\SelectFilter::make('event_type')->options(fn () => SecurityEvent::query()->distinct()->orderBy('event_type')->pluck('event_type', 'event_type')->all()),
        ])->actions([Tables\Actions\ViewAction::make()])->defaultSort('occurred_at', 'desc');
    }

    public static function canCreate(): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function getPages(): array { return ['index' => Pages\ListSecurityEvents::route('/'), 'view' => Pages\ViewSecurityEvent::route('/{record}')]; }
}
