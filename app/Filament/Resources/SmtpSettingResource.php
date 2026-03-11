<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SmtpSettingResource\Pages;
use App\Models\SmtpSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SmtpSettingResource extends Resource
{
    protected static ?string $model = SmtpSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-server';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'SMTP Settings';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('SMTP Configuration')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('SMTP Settings')
                            ->schema([
                                Forms\Components\Section::make('Connection Settings')
                                    ->schema([
                                        Forms\Components\Select::make('mailer')
                                            ->options([
                                                'smtp' => 'SMTP',
                                                'mailgun' => 'Mailgun',
                                                'ses' => 'Amazon SES',
                                            ])
                                            ->default('smtp')
                                            ->required(),
                                        Forms\Components\TextInput::make('host')
                                            ->required()
                                            ->maxLength(255)
                                            ->label('SMTP Host'),
                                        Forms\Components\TextInput::make('port')
                                            ->required()
                                            ->numeric()
                                            ->default(465)
                                            ->label('SMTP Port'),
                                        Forms\Components\Select::make('encryption')
                                            ->options([
                                                'ssl' => 'SSL',
                                                'tls' => 'TLS',
                                            ])
                                            ->default('ssl')
                                            ->required(),
                                    ]),
                                Forms\Components\Section::make('Authentication')
                                    ->schema([
                                        Forms\Components\TextInput::make('username')
                                            ->required()
                                            ->maxLength(255)
                                            ->label('SMTP Username'),
                                        Forms\Components\TextInput::make('password')
                                            ->required()
                                            ->password()
                                            ->label('SMTP Password'),
                                    ]),
                                Forms\Components\Section::make('From Address')
                                    ->schema([
                                        Forms\Components\TextInput::make('from_address')
                                            ->required()
                                            ->email()
                                            ->maxLength(255)
                                            ->label('From Email Address'),
                                        Forms\Components\TextInput::make('from_name')
                                            ->maxLength(255)
                                            ->label('From Name')
                                            ->helperText('Optional. Defaults to app name.'),
                                    ]),
                                Forms\Components\Toggle::make('is_active')
                                    ->default(true)
                                    ->label('Active')
                                    ->helperText('Only one SMTP setting can be active at a time.'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('host')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('port')
                    ->sortable(),
                Tables\Columns\TextColumn::make('username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('from_address')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSmtpSettings::route('/'),
            'create' => Pages\CreateSmtpSetting::route('/create'),
            'edit' => Pages\EditSmtpSetting::route('/{record}/edit'),
        ];
    }
}
