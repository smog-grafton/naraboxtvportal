<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SecurityRuleResource\Pages;
use App\Models\SecurityRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SecurityRuleResource extends Resource
{
    protected static ?string $model = SecurityRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Security';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Matching rule')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\Select::make('identity_type')->options(array_combine(
                    ['DISPLAY_NAME', 'USERNAME', 'EMAIL', 'EMAIL_DOMAIN', 'PHONE', 'GOOGLE_SUB', 'SOCIAL_PROVIDER_ID', 'APPLE_SUB', 'PAYER_PHONE', 'IP', 'CIDR', 'DEVICE_ID'],
                    ['Display name', 'Username', 'Email', 'Email domain', 'Phone', 'Google provider ID', 'Social provider ID', 'Apple provider ID', 'Payer phone', 'IP', 'CIDR', 'Device ID']
                ))->required(),
                Forms\Components\Select::make('matching_mode')->options([
                    'EXACT' => 'Exact', 'NORMALIZED_EXACT' => 'Normalized exact', 'TOKEN_SET' => 'Token set',
                    'CONTAINS' => 'Contains', 'PREFIX' => 'Prefix', 'SUFFIX' => 'Suffix', 'REGEX' => 'Regular expression',
                ])->required(),
                Forms\Components\Textarea::make('pattern')->required()->maxLength(500)->helperText('Token-set matching is ideal for precise full-name rules. Regex is restricted to administrators and execution limits are enforced.')->columnSpanFull(),
                Forms\Components\Select::make('action')->options([
                    'ALERT_ONLY' => 'Alert only', 'BLOCK_REGISTRATION' => 'Block registration', 'BLOCK_LOGIN' => 'Block login',
                    'BLOCK_PAYMENT' => 'Block payment', 'PAYMENT_RESTRICT' => 'Payment restrict', 'SUSPEND' => 'Suspend', 'BAN' => 'Ban',
                ])->required(),
                Forms\Components\Select::make('risk_level')->options(array_combine(['NORMAL', 'WATCH', 'ELEVATED', 'HIGH', 'CRITICAL'], ['Normal', 'Watch', 'Elevated', 'High', 'Critical']))->required(),
                Forms\Components\Toggle::make('enabled')->default(true),
                Forms\Components\TextInput::make('priority')->numeric()->default(100)->required(),
                Forms\Components\DateTimePicker::make('expires_at'),
                Forms\Components\Textarea::make('reason')->required()->columnSpanFull(),
                Forms\Components\Textarea::make('notes')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable()->wrap(),
            Tables\Columns\TextColumn::make('identity_type')->badge(),
            Tables\Columns\TextColumn::make('matching_mode')->badge(),
            Tables\Columns\TextColumn::make('pattern')->searchable()->limit(40),
            Tables\Columns\TextColumn::make('action')->badge()->color('danger'),
            Tables\Columns\TextColumn::make('risk_level')->badge()->color(fn (string $state) => match ($state) {
                'CRITICAL' => 'danger', 'HIGH' => 'warning', default => 'gray'
            }),
            Tables\Columns\IconColumn::make('enabled')->boolean(),
            Tables\Columns\TextColumn::make('hit_count')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('last_hit_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('expires_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\TernaryFilter::make('enabled'),
            Tables\Filters\SelectFilter::make('action')->options([
                'BLOCK_REGISTRATION' => 'Block registration', 'BLOCK_LOGIN' => 'Block login', 'BLOCK_PAYMENT' => 'Block payment',
                'PAYMENT_RESTRICT' => 'Payment restrict', 'SUSPEND' => 'Suspend', 'BAN' => 'Ban',
            ]),
        ])->actions([Tables\Actions\EditAction::make()])->defaultSort('priority', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSecurityRules::route('/'), 'create' => Pages\CreateSecurityRule::route('/create'), 'edit' => Pages\EditSecurityRule::route('/{record}/edit')];
    }
}
