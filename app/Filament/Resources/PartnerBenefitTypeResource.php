<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerBenefitTypeResource\Pages;
use App\Models\PartnerBenefitType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerBenefitTypeResource extends Resource
{
    protected static ?string $model = PartnerBenefitType::class;
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationGroup = 'Partner Program';
    protected static ?string $navigationLabel = 'Benefit Types';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('code')
                ->required()->maxLength(64)->unique(ignoreRecord: true)
                ->helperText('Stable system code, for example FIRST_MOVIE_FREE.'),
            Forms\Components\Toggle::make('globally_enabled')
                ->label('Enabled across the platform')
                ->helperText('The global kill switch. Partner-level offers cannot run while this is off.'),
            Forms\Components\Textarea::make('description')->columnSpanFull(),
            Forms\Components\KeyValue::make('default_configuration')
                ->helperText('Canonical money/rate values use integer UGX and basis points (10% = 1000 bps).')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('code')->copyable()->searchable(),
            Tables\Columns\IconColumn::make('globally_enabled')->boolean()->label('Global'),
            Tables\Columns\TextColumn::make('benefits_count')->counts('benefits')->label('Partner offers'),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartnerBenefitTypes::route('/'),
            'create' => Pages\CreatePartnerBenefitType::route('/create'),
            'edit' => Pages\EditPartnerBenefitType::route('/{record}/edit'),
        ];
    }
}
