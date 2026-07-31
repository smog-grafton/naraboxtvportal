<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinancialSettingResource\Pages;
use App\Models\CreatorPayoutOption;
use App\Models\FinancialSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FinancialSettingResource extends Resource
{
    protected static ?string $model = FinancialSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Creator Economy Settings';

    protected static ?string $navigationGroup = 'Creator Finance';

    public static function form(Form $form): Form
    {
        $percentage = fn (Forms\Components\TextInput $field) => $field
            ->numeric()
            ->minValue(0)
            ->maxValue(100)
            ->step(0.01)
            ->suffix('%')
            ->formatStateUsing(fn ($state) => $state !== null ? ((int) $state) / 100 : null)
            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100));

        return $form->schema([
            Forms\Components\Section::make('Revenue sharing')
                ->description('Money is stored as integer minor units. Creator share and platform share must total exactly 100%.')
                ->schema([
                    Forms\Components\Select::make('currency')
                        ->options(['UGX' => 'UGX — Uganda shilling'])
                        ->default('UGX')
                        ->required(),
                    Forms\Components\Select::make('settlement_frequency')
                        ->options([
                            'monthly' => 'Monthly',
                            'weekly' => 'Weekly',
                            'manual' => 'Manual',
                        ])
                        ->default('monthly')
                        ->required(),
                    $percentage(Forms\Components\TextInput::make('creator_share_bps')->label('Default creator share'))->required(),
                    $percentage(Forms\Components\TextInput::make('platform_share_bps')->label('Default platform share'))->required(),
                    $percentage(Forms\Components\TextInput::make('subscription_pool_bps')->label('Subscription creator pool'))->required(),
                    Forms\Components\TextInput::make('creator_hold_days')->numeric()->minValue(0)->suffix('days')->required(),
                    Forms\Components\TextInput::make('qualified_watch_seconds')->numeric()->minValue(1)->suffix('seconds')->required(),
                    Forms\Components\TextInput::make('max_qualified_plays_per_day')->numeric()->minValue(1)->required(),
                ])->columns(2),
            Forms\Components\Section::make('Withdrawals')
                ->description('Automatic payout remains unavailable until an automatic provider reports configured and enabled.')
                ->schema([
                    Forms\Components\Placeholder::make('automatic_provider_readiness')
                        ->label('Automatic provider readiness')
                        ->content(fn () => CreatorPayoutOption::query()
                            ->where('automation_type', 'automatic')
                            ->where('is_configured', true)
                            ->where('is_enabled', true)
                            ->exists()
                                ? 'Ready — at least one automatic payout provider is configured and enabled.'
                                : 'Not ready — configure and enable an automatic payout provider under Payout Methods.'),
                    Forms\Components\TextInput::make('min_withdrawal_minor')->numeric()->minValue(0)->suffix('UGX')->required(),
                    Forms\Components\TextInput::make('max_withdrawal_minor')->numeric()->minValue(0)->suffix('UGX'),
                    Forms\Components\TextInput::make('withdrawal_fee_minor')->numeric()->minValue(0)->suffix('UGX')->required(),
                    Forms\Components\TextInput::make('daily_withdrawal_limit_minor')->numeric()->minValue(0)->suffix('UGX'),
                    Forms\Components\TextInput::make('monthly_withdrawal_limit_minor')->numeric()->minValue(0)->suffix('UGX'),
                    Forms\Components\CheckboxList::make('allowed_payout_methods')
                        ->options([
                            'mobile_money' => 'Mobile money',
                            'bank' => 'Bank transfer',
                            'other' => 'Other manual payout',
                        ])
                        ->columns(2),
                    Forms\Components\Toggle::make('manual_payout_enabled')->default(true),
                    Forms\Components\Toggle::make('manual_approval_required')->default(true),
                    Forms\Components\Toggle::make('auto_payout_enabled')
                        ->helperText('Validated against the Payout Methods catalog when saved.'),
                    Forms\Components\Toggle::make('iotec_disbursement_enabled')
                        ->label('ioTec disbursement enabled')
                        ->helperText('This legacy execution gate is separate from provider readiness. Both must be enabled.'),
                ])->columns(2),
            Forms\Components\Section::make('Earnings policy')
                ->schema([
                    Forms\Components\Toggle::make('earnings_pending_until_approval')->default(true),
                    Forms\Components\Toggle::make('unverified_creator_earns')
                        ->helperText('Keep disabled unless policy and legal review explicitly allow it.'),
                    Forms\Components\Select::make('unverified_earnings_policy')
                        ->options([
                            'platform_retains' => 'Platform retains',
                            'hold_pending_verification' => 'Hold pending verification',
                            'forfeit' => 'Forfeit',
                        ])
                        ->default('platform_retains')
                        ->required(),
                    Forms\Components\Textarea::make('refund_chargeback_policy')
                        ->rows(4)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('creator_share_bps')
                    ->label('Creator share')->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2).'%'),
                Tables\Columns\TextColumn::make('platform_share_bps')
                    ->label('Platform share')->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2).'%'),
                Tables\Columns\TextColumn::make('subscription_pool_bps')
                    ->label('Subscription pool')->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2).'%'),
                Tables\Columns\TextColumn::make('min_withdrawal_minor')->label('Min withdrawal')->money('UGX', divideBy: 1),
                Tables\Columns\TextColumn::make('settlement_frequency')->badge(),
                Tables\Columns\IconColumn::make('manual_payout_enabled')->boolean()->label('Manual'),
                Tables\Columns\IconColumn::make('auto_payout_enabled')->boolean()->label('Automatic'),
                Tables\Columns\TextColumn::make('updated_at')->label('Last changed')->dateTime()->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinancialSettings::route('/'),
            'create' => Pages\CreateFinancialSetting::route('/create'),
            'edit' => Pages\EditFinancialSetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return FinancialSetting::query()->doesntExist();
    }
}
