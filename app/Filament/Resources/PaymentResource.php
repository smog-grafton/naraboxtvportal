<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Services\EmailService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Storage;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Manual Payments';
    protected static ?string $modelLabel = 'Payment';
    protected static ?string $pluralModelLabel = 'Manual Payments';
    protected static ?string $navigationGroup = 'Payment Management';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->disabled(),
                Forms\Components\Select::make('transaction_id')
                    ->relationship('transaction', 'transaction_ref')
                    ->required()
                    ->disabled(),
                Forms\Components\Select::make('payment_gateway_id')
                    ->relationship('paymentGateway', 'display_name')
                    ->required()
                    ->disabled(),
                Forms\Components\FileUpload::make('proof_path')
                    ->label('Proof of Payment')
                    ->image()
                    ->directory('payment-proofs')
                    ->acceptedFileTypes(['image/*', 'application/pdf'])
                    ->maxSize(10240) // 10MB
                    ->downloadable()
                    ->disabled(),
                Forms\Components\Textarea::make('notes')
                    ->label('User Notes')
                    ->rows(3)
                    ->disabled(),
                Forms\Components\Select::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'APPROVED' => 'Approved',
                        'REJECTED' => 'Rejected',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $record, Forms\Set $set) {
                        if ($state === 'APPROVED' && $record && $record->status !== 'APPROVED') {
                            $set('approved_by', auth()->id());
                            $set('approved_at', now());
                            \App\Services\PaymentApprovalService::approvePayment($record->fresh());
                        }
                    }),
                Forms\Components\Textarea::make('admin_notes')
                    ->label('Admin Notes')
                    ->rows(3)
                    ->visible(fn (Forms\Get $get) => in_array($get('status'), ['APPROVED', 'REJECTED'])),
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
                Tables\Columns\TextColumn::make('transaction.transaction_ref')
                    ->label('Transaction Ref')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('transaction.amount')
                    ->label('Amount')
                    ->money('UGX')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paymentGateway.display_name')
                    ->label('Gateway')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('proof_path')
                    ->label('Proof')
                    ->size(50)
                    ->circular(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'PENDING',
                        'success' => 'APPROVED',
                        'danger' => 'REJECTED',
                    ]),
                Tables\Columns\TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->default('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'APPROVED' => 'Approved',
                        'REJECTED' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('payment_gateway_id')
                    ->label('Gateway')
                    ->relationship('paymentGateway', 'display_name'),
            ])
            ->actions([
                Action::make('view_proof')
                    ->label('View Proof')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Payment $record) => Storage::url($record->proof_path))
                    ->openUrlInNewTab(),
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
            'index' => Pages\ListPayments::route('/'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
