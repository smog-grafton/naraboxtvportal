<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminAlertSettingResource\Pages;
use App\Models\AdminAlertSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdminAlertSettingResource extends Resource
{
    protected static ?string $model = AdminAlertSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Admin Alert Settings';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('alert_email')->email()->required(),
            Forms\Components\Toggle::make('alert_on_registration')->default(true),
            Forms\Components\Toggle::make('alert_on_payment_success')->default(true),
            Forms\Components\Toggle::make('alert_on_payment_failure')->default(true),
            Forms\Components\Toggle::make('alert_on_content_request')->default(true),
            Forms\Components\Toggle::make('alert_on_comment')->default(true),
            Forms\Components\Toggle::make('alert_on_comment_reply')->default(true),
            Forms\Components\Toggle::make('alert_on_playback_issue')->default(true),
            Forms\Components\Toggle::make('alert_on_campaign_summary')->default(true),
            Forms\Components\TextInput::make('playback_failure_threshold')->numeric()->required(),
            Forms\Components\TextInput::make('slow_start_threshold_ms')->numeric()->required(),
            Forms\Components\TextInput::make('high_failure_rate_threshold')->numeric()->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('alert_email')->searchable(),
            Tables\Columns\TextColumn::make('playback_failure_threshold')->numeric(),
            Tables\Columns\TextColumn::make('slow_start_threshold_ms')->numeric(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminAlertSettings::route('/'),
            'create' => Pages\CreateAdminAlertSetting::route('/create'),
            'edit' => Pages\EditAdminAlertSetting::route('/{record}/edit'),
        ];
    }
}
