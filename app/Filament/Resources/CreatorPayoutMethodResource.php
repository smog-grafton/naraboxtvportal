<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorPayoutMethodResource\Pages;
use App\Models\CreatorPayoutOption;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CreatorPayoutMethodResource extends Resource
{
    protected static ?string $model = CreatorPayoutOption::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Payout Methods';

    protected static ?string $modelLabel = 'Payout Method';

    protected static ?string $pluralModelLabel = 'Payout Methods';

    protected static ?string $navigationGroup = 'Creator Finance';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Payout rail')
                ->description('This catalog controls the payout choices available to creators. Creator-owned account details are managed under Payout Accounts.')
                ->schema([
                    Forms\Components\TextInput::make('label')->required()->maxLength(255),
                    Forms\Components\TextInput::make('key')
                        ->required()
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->disabledOn('edit'),
                    Forms\Components\Select::make('category')->options([
                        'mobile_money' => 'Mobile money',
                        'bank' => 'Bank transfer',
                        'mobile_money_and_bank' => 'Mobile money and bank',
                        'other' => 'Other',
                    ])->required(),
                    Forms\Components\Select::make('automation_type')
                        ->options(['automatic' => 'Automatic', 'manual' => 'Manual'])
                        ->required()
                        ->live(),
                    Forms\Components\TextInput::make('adapter')
                        ->helperText('Internal adapter key. Leave blank for manual payout methods.'),
                    Forms\Components\TextInput::make('sort_order')->numeric()->minValue(0)->default(0),
                    Forms\Components\TagsInput::make('supported_countries')
                        ->placeholder('UG')
                        ->helperText('ISO two-letter country codes.'),
                    Forms\Components\TagsInput::make('supported_currencies')
                        ->placeholder('UGX')
                        ->helperText('ISO three-letter currency codes.'),
                ])->columns(2),
            Forms\Components\Section::make('Limits and availability')
                ->schema([
                    Forms\Components\TextInput::make('minimum_amount_minor')->label('Minimum (whole UGX)')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('maximum_amount_minor')->label('Maximum (whole UGX)')->numeric()->minValue(0),
                    Forms\Components\TextInput::make('fee_minor')->label('Fee (whole UGX)')->numeric()->minValue(0)->default(0),
                    Forms\Components\Toggle::make('is_enabled')
                        ->helperText('Automatic rails cannot be enabled until configuration is complete.'),
                    Forms\Components\TextInput::make('configuration_status')->disabled(),
                    Forms\Components\Textarea::make('configuration_notes')->disabled()->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Availability catalog for creator payouts. “Automatic” describes a real provider integration; it does not mean it is configured or enabled.')
            ->columns([
                Tables\Columns\TextColumn::make('label')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('category')->badge(),
                Tables\Columns\TextColumn::make('automation_type')
                    ->label('Mode')
                    ->badge()
                    ->color(fn (string $state) => $state === 'automatic' ? 'info' : 'gray'),
                Tables\Columns\IconColumn::make('is_configured')->label('Configured')->boolean(),
                Tables\Columns\TextColumn::make('configuration_status')
                    ->label('Configuration')
                    ->badge()
                    ->color(fn (string $state) => $state === 'configured' || $state === 'not_required' ? 'success' : 'warning'),
                Tables\Columns\IconColumn::make('is_enabled')->label('Available')->boolean(),
                Tables\Columns\TextColumn::make('supported_currencies')->badge()->separator(','),
                Tables\Columns\TextColumn::make('minimum_amount_minor')->label('Minimum')->money('UGX', divideBy: 1),
                Tables\Columns\TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('automation_type')->options([
                    'automatic' => 'Automatic',
                    'manual' => 'Manual',
                ]),
                Tables\Filters\TernaryFilter::make('is_enabled')->label('Available'),
                Tables\Filters\TernaryFilter::make('is_configured')->label('Configured'),
            ])
            ->actions([
                Tables\Actions\Action::make('refresh_configuration')
                    ->label('Check configuration')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (CreatorPayoutOption $record) => $record->automation_type === 'automatic')
                    ->action(fn (CreatorPayoutOption $record) => $record->refreshConfigurationStatus()),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreatorPayoutMethods::route('/'),
            'create' => Pages\CreateCreatorPayoutMethod::route('/create'),
            'edit' => Pages\EditCreatorPayoutMethod::route('/{record}/edit'),
        ];
    }
}
