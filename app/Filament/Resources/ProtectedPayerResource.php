<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProtectedPayerResource\Pages;
use App\Models\ProtectedPayer;
use App\Services\IdentityNormalizer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProtectedPayerResource extends Resource
{
    protected static ?string $model = ProtectedPayer::class;
    protected static ?string $navigationIcon = 'heroicon-o-phone-x-mark';
    protected static ?string $navigationGroup = 'Security';
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('normalized_phone')->label('Payer phone')->tel()->required()->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn ($state) => app(IdentityNormalizer::class)->phone((string) $state)),
            Forms\Components\TextInput::make('network')->maxLength(32),
            Forms\Components\TextInput::make('country')->default('UG')->maxLength(2)->required(),
            Forms\Components\Select::make('status')->options(['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive'])->default('ACTIVE')->required(),
            Forms\Components\Select::make('reason')->options(array_combine(
                ['OWNER_REPORTED_UNAUTHORIZED', 'SUSPECTED_ABUSE_TARGET', 'CONFIRMED_ABUSE_TARGET', 'CUSTOMER_REQUEST', 'FRAUD_INVESTIGATION', 'ADMIN_BLOCK'],
                ['Owner reported unauthorized', 'Suspected abuse target', 'Confirmed abuse target', 'Customer request', 'Fraud investigation', 'Admin block']
            ))->required(),
            Forms\Components\DateTimePicker::make('expires_at'),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('normalized_phone')->searchable()->copyable(),
            Tables\Columns\TextColumn::make('network'),
            Tables\Columns\TextColumn::make('reason')->badge(),
            Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => $state === 'ACTIVE' ? 'danger' : 'gray'),
            Tables\Columns\TextColumn::make('attempt_count')->numeric()->sortable(),
            Tables\Columns\TextColumn::make('last_attempt_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('expires_at')->dateTime(),
        ])->actions([Tables\Actions\EditAction::make()])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProtectedPayers::route('/'), 'create' => Pages\CreateProtectedPayer::route('/create'), 'edit' => Pages\EditProtectedPayer::route('/{record}/edit')];
    }
}
