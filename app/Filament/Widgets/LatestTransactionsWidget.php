<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PaymentTransactionResource;
use App\Models\PaymentTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestTransactionsWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PaymentTransaction::query()
                    ->with(['user', 'paymentGateway'])
                    ->latest('created_at')
            )
            ->heading('Latest Transactions')
            ->description('Most recent payment activity across the platform.')
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
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'BUY' => 'success',
                        'RENT' => 'primary',
                        'SUBSCRIPTION' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')
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
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ]);
    }
}
