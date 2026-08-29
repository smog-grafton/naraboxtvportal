<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerBenefitResource\Pages;
use App\Models\PartnerBenefit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerBenefitResource extends Resource
{
    protected static ?string $model = PartnerBenefit::class;
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationGroup = 'Partner Program';
    protected static ?string $navigationLabel = 'Partner Benefits';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('partner_id')
                ->relationship('partner', 'display_name')->searchable()->preload()->required(),
            Forms\Components\Select::make('benefit_type_id')
                ->relationship('benefitType', 'name')->searchable()->preload()->required(),
            Forms\Components\Toggle::make('enabled')->helperText('Partner-level switch.'),
            Forms\Components\DateTimePicker::make('starts_at'),
            Forms\Components\DateTimePicker::make('ends_at')->after('starts_at'),
            Forms\Components\KeyValue::make('configuration')
                ->helperText('Overrides the type defaults. Discount rates are basis points (10% = 1000); money is whole UGX.')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('partner.display_name')->label('Partner')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('benefitType.name')->label('Benefit')->searchable()->sortable(),
            Tables\Columns\IconColumn::make('enabled')->boolean(),
            Tables\Columns\IconColumn::make('benefitType.globally_enabled')->boolean()->label('Global'),
            Tables\Columns\TextColumn::make('claims_count')->counts('claims')->label('Uses')->sortable(),
            Tables\Columns\TextColumn::make('starts_at')->dateTime()->placeholder('Immediately'),
            Tables\Columns\TextColumn::make('ends_at')->dateTime()->placeholder('No expiry'),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartnerBenefits::route('/'),
            'create' => Pages\CreatePartnerBenefit::route('/create'),
            'edit' => Pages\EditPartnerBenefit::route('/{record}/edit'),
        ];
    }
}
