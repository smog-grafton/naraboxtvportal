<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PaymentTransactionResource;
use App\Models\PaymentTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PendingTransactionsWidget extends TableWidget
{
    protected static ?int $sort = 6;

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PaymentTransaction::query()
                    ->with(['user', 'paymentGateway'])
                    ->where('status', 'PENDING')
                    ->latest('created_at')
            )
            ->heading('Pending Transactions')
            ->description('Transactions that still need follow-up or completion.')
            ->poll('60s')
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10])
            ->recordUrl(fn (PaymentTransaction $record): string => PaymentTransactionResource::getUrl('edit', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('transaction_ref')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('Unknown')
                    ->searchable(),
                Tables\Columns\TextColumn::make('paymentGateway.display_name')
                    ->label('Gateway')
                    ->placeholder('Unknown'),
                Tables\Columns\TextColumn::make('amount')
                    ->money('UGX')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->emptyStateHeading('No pending transactions')
            ->emptyStateDescription('Everything is currently cleared or already resolved.');
    }
}
