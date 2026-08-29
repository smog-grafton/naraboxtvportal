<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SecurityIdentityResource\Pages;
use App\Models\SecurityIdentity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SecurityIdentityResource extends Resource
{
    protected static ?string $model = SecurityIdentity::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationGroup = 'Security';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Security Identities';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Persistent identity control')->schema([
                Forms\Components\Select::make('identity_type')->options(array_combine(
                    ['USER_ID', 'EMAIL', 'PHONE', 'GOOGLE_SUB', 'SOCIAL_PROVIDER_ID', 'APPLE_SUB', 'PAYER_PHONE', 'IP', 'CIDR', 'DEVICE_ID'],
                    ['User ID', 'Email', 'Phone', 'Google provider ID', 'Social provider ID', 'Apple provider ID', 'Payer phone', 'IP', 'CIDR', 'Device ID']
                ))->required(),
                Forms\Components\TextInput::make('normalized_value')
                    ->label('Identity value')->required()->maxLength(512)
                    ->helperText('Values are normalized and indexed by SHA-256 automatically. Use temporary expiry for IP/CIDR controls.'),
                Forms\Components\Select::make('action')->options([
                    'ALERT_ONLY' => 'Alert only', 'BLOCK_REGISTRATION' => 'Block registration',
                    'BLOCK_LOGIN' => 'Block login', 'BLOCK_PAYMENT' => 'Block payment',
                    'PAYMENT_RESTRICT' => 'Payment restrict', 'SUSPEND' => 'Suspend', 'BAN' => 'Ban',
                ])->required(),
                Forms\Components\Select::make('risk_level')->options(array_combine(
                    ['NORMAL', 'WATCH', 'ELEVATED', 'HIGH', 'CRITICAL'],
                    ['Normal', 'Watch', 'Elevated', 'High', 'Critical']
                ))->required()->default('HIGH'),
                Forms\Components\Toggle::make('enabled')->default(true),
                Forms\Components\DateTimePicker::make('expires_at'),
                Forms\Components\Textarea::make('reason')->required()->columnSpanFull(),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
                Forms\Components\Hidden::make('source')->default('MANUAL'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('identity_type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('normalized_value')->label('Value')->searchable()->copyable()->limit(45),
                Tables\Columns\TextColumn::make('action')->badge()->color('danger'),
                Tables\Columns\TextColumn::make('risk_level')->badge(),
                Tables\Columns\IconColumn::make('enabled')->boolean(),
                Tables\Columns\TextColumn::make('source')->badge(),
                Tables\Columns\TextColumn::make('reason')->limit(60)->wrap(),
                Tables\Columns\TextColumn::make('hit_count')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('last_hit_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('enabled'),
                Tables\Filters\SelectFilter::make('identity_type')->options(fn () => SecurityIdentity::query()->distinct()->orderBy('identity_type')->pluck('identity_type', 'identity_type')->all()),
                Tables\Filters\SelectFilter::make('action')->options(fn () => SecurityIdentity::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSecurityIdentities::route('/'),
            'create' => Pages\CreateSecurityIdentity::route('/create'),
            'edit' => Pages\EditSecurityIdentity::route('/{record}/edit'),
        ];
    }
}
