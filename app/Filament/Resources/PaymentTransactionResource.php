<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentTransactionResource\Pages;
use App\Models\PaymentTransaction;
use App\Services\PawaPayService;
use App\Services\PaymentApprovalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentTransactionResource extends Resource
{
    protected static ?string $model = PaymentTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Transactions';
    protected static ?string $modelLabel = 'Transaction';
    protected static ?string $pluralModelLabel = 'Transactions';
    protected static ?string $navigationGroup = 'Payment Management';
    protected static ?int $navigationSort = 3;
    protected static bool $shouldRegisterNavigation = true;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable(),
                Forms\Components\Select::make('payment_gateway_id')
                    ->relationship('paymentGateway', 'display_name')
                    ->required(),
                Forms\Components\TextInput::make('gateway_code')
                    ->maxLength(255),
                Forms\Components\Select::make('type')
                    ->options([
                        'RENT' => 'Rent',
                        'BUY' => 'Buy',
                        'SUBSCRIPTION' => 'Subscription',
                    ])
                    ->required()
                    ->live(),
                Forms\Components\Select::make('subscription_plan_id')
                    ->relationship('subscriptionPlan', 'name')
                    ->required(fn (Forms\Get $get) => $get('type') === 'SUBSCRIPTION')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'SUBSCRIPTION'),
                Forms\Components\TextInput::make('transaction_ref')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix('UGX'),
                Forms\Components\Select::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'SUCCESS' => 'Success',
                        'FAILED' => 'Failed',
                        'CANCELLED' => 'Cancelled',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $record, Forms\Set $set) {
                        if ($state === 'SUCCESS' && $record && $record->status !== 'SUCCESS') {
                            \App\Services\PaymentApprovalService::approveTransaction($record->fresh());
                        }
                    }),
                Forms\Components\TextInput::make('gateway_transaction_id')
                    ->maxLength(255),
                Forms\Components\TextInput::make('external_reference')
                    ->label('External Reference')
                    ->maxLength(255),
                Forms\Components\TextInput::make('provider_code')
                    ->maxLength(255),
                Forms\Components\TextInput::make('failure_reason')
                    ->maxLength(255),
                Forms\Components\Textarea::make('notes')
                    ->rows(3),
                Forms\Components\Textarea::make('raw_request')
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : null)
                    ->rows(8)
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('raw_response')
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : null)
                    ->rows(8)
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('raw_callback')
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT) : null)
                    ->rows(8)
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscriptionPlan.name')
                    ->label('Plan')
                    ->visible(fn ($record) => $record?->type === 'SUBSCRIPTION')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paymentGateway.display_name')
                    ->label('Gateway')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gateway_code')
                    ->label('Gateway Code')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'RENT',
                        'success' => 'BUY',
                        'warning' => 'SUBSCRIPTION',
                    ]),
                Tables\Columns\TextColumn::make('transaction_ref')
                    ->label('Reference')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('external_reference')
                    ->label('External Ref')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('provider_code')
                    ->label('Provider')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('UGX')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'PENDING',
                        'success' => 'SUCCESS',
                        'danger' => 'FAILED',
                        'gray' => 'CANCELLED',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'RENT' => 'Rent',
                        'BUY' => 'Buy',
                        'SUBSCRIPTION' => 'Subscription',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'SUCCESS' => 'Success',
                        'FAILED' => 'Failed',
                        'CANCELLED' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('payment_gateway_id')
                    ->label('Gateway')
                    ->relationship('paymentGateway', 'display_name'),
            ])
            ->actions([
                Tables\Actions\Action::make('recheck_pawapay')
                    ->label('Recheck Status')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (PaymentTransaction $record) => ($record->gateway_code === 'pawapay' || $record->paymentGateway?->slug === 'pawapay') && $record->external_reference && $record->status === 'PENDING')
                    ->action(function (PaymentTransaction $record) {
                        $service = app(PawaPayService::class);
                        $result = $service->checkDepositStatus($record->external_reference);
                        $normalized = $result['normalized_status'] ?? 'PENDING';
                        $body = $result['body'] ?? [];
                        $failureReason = $service->extractFailureReason($body);

                        $record->update([
                            'status' => $normalized,
                            'failure_reason' => $failureReason,
                            'raw_response' => $body,
                            'gateway_response' => array_merge($record->gateway_response ?? [], ['filament_recheck' => $body]),
                        ]);

                        if ($normalized === 'SUCCESS') {
                            PaymentApprovalService::grantAccess($record->fresh());
                        }

                        Notification::make()
                            ->title('PawaPay status rechecked')
                            ->body('Current status: ' . $normalized)
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentTransactions::route('/'),
            'create' => Pages\CreatePaymentTransaction::route('/create'),
            'edit' => Pages\EditPaymentTransaction::route('/{record}/edit'),
        ];
    }
}
