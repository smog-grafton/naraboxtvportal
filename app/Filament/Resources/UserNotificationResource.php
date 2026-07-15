<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserNotificationResource\Pages;
use App\Models\UserNotification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserNotificationResource extends Resource
{
    protected static ?string $model = UserNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationGroup = 'Engagement';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->required(),
            Forms\Components\Textarea::make('message')->required()->columnSpanFull(),
            Forms\Components\Select::make('type')
                ->options([
                    'new_movie' => 'New movie',
                    'new_show' => 'New show',
                    'new_episode' => 'New episode',
                    'promotion' => 'Promotion',
                    'payment' => 'Payment',
                    'system' => 'System',
                ])
                ->required(),
            Forms\Components\Select::make('user_id')->relationship('user', 'name')->searchable()->nullable(),
            Forms\Components\Toggle::make('is_global')->default(false),
            Forms\Components\TextInput::make('image_url')->url(),
            Forms\Components\TextInput::make('action_url'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\IconColumn::make('is_global')->boolean(),
            Tables\Columns\TextColumn::make('user.name')->label('User'),
            Tables\Columns\TextColumn::make('read_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserNotifications::route('/'),
            'create' => Pages\CreateUserNotification::route('/create'),
            'edit' => Pages\EditUserNotification::route('/{record}/edit'),
        ];
    }
}
