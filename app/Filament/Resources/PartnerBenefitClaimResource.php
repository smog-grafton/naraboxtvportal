<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerBenefitClaimResource\Pages;
use App\Models\PartnerBenefitClaim;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerBenefitClaimResource extends Resource
{
    protected static ?string $model = PartnerBenefitClaim::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Partner Program';
    protected static ?string $navigationLabel = 'Benefit Redemptions';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('user.name')->label('Viewer')->searchable(),
            Tables\Columns\TextColumn::make('partner.display_name')->label('Partner')->searchable(),
            Tables\Columns\TextColumn::make('benefit.benefitType.name')->label('Benefit'),
            Tables\Columns\TextColumn::make('movie.title')->placeholder('Payment / subscription'),
            Tables\Columns\TextColumn::make('transaction.transaction_ref')->label('Transaction')->copyable()->placeholder('No paid transaction'),
            Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                'redeemed' => 'success', 'reserved' => 'warning', 'reversed', 'failed' => 'danger', default => 'gray',
            }),
            Tables\Columns\TextColumn::make('claimed_at')->dateTime()->placeholder('Reserved')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPartnerBenefitClaims::route('/')];
    }
}
