<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorSettlementResource\Pages;
use App\Filament\Resources\CreatorSettlementResource\RelationManagers\AllocationsRelationManager;
use App\Models\CreatorSettlement;
use App\Services\CreatorSettlementService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CreatorSettlementResource extends Resource
{
    protected static ?string $model = CreatorSettlement::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Subscription Settlements';

    protected static ?string $navigationGroup = 'Creator Finance';

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Settlement summary')
                ->schema([
                    Infolists\Components\TextEntry::make('period'),
                    Infolists\Components\TextEntry::make('settlement_type')->badge(),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('metric')->badge(),
                    Infolists\Components\TextEntry::make('eligible_revenue_minor')->money('UGX', divideBy: 1),
                    Infolists\Components\TextEntry::make('deductions_minor')->money('UGX', divideBy: 1),
                    Infolists\Components\TextEntry::make('creator_pool_bps')
                        ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2).'%'),
                    Infolists\Components\TextEntry::make('creator_pool_minor')->money('UGX', divideBy: 1),
                    Infolists\Components\TextEntry::make('allocated_minor')->money('UGX', divideBy: 1),
                    Infolists\Components\TextEntry::make('discrepancy_minor')
                        ->money('UGX', divideBy: 1)
                        ->color(fn ($state) => ((int) $state) === 0 ? 'success' : 'danger'),
                    Infolists\Components\TextEntry::make('creator_count')->numeric(),
                    Infolists\Components\TextEntry::make('total_qualified_metric')->numeric(),
                ])->columns(3),
            Infolists\Components\Section::make('Review trail')
                ->schema([
                    Infolists\Components\TextEntry::make('generated_at')->dateTime(),
                    Infolists\Components\TextEntry::make('approved_at')->dateTime(),
                    Infolists\Components\TextEntry::make('finalized_at')->dateTime(),
                    Infolists\Components\TextEntry::make('cancelled_at')->dateTime(),
                    Infolists\Components\TextEntry::make('admin_notes')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('failure_reason')->columnSpanFull(),
                    Infolists\Components\KeyValueEntry::make('calculation_snapshot')->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('settlement_type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('eligible_revenue_minor')->money('UGX', divideBy: 1)->label('Eligible revenue'),
                Tables\Columns\TextColumn::make('creator_pool_bps')
                    ->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2).'%')
                    ->label('Pool'),
                Tables\Columns\TextColumn::make('creator_pool_minor')->money('UGX', divideBy: 1)->label('Creator pool'),
                Tables\Columns\TextColumn::make('allocated_minor')->money('UGX', divideBy: 1)->label('Allocated'),
                Tables\Columns\TextColumn::make('discrepancy_minor')
                    ->money('UGX', divideBy: 1)
                    ->label('Difference')
                    ->color(fn ($state) => ((int) $state) === 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('creator_count')->label('Creators')->numeric(),
                Tables\Columns\TextColumn::make('total_qualified_metric')->label('Qualified seconds')->numeric(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'finalized' => 'success',
                    'approved' => 'info',
                    'under_review' => 'warning',
                    'cancelled', 'failed' => 'danger',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('generated_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'calculating' => 'Calculating',
                    'under_review' => 'Under review',
                    'approved' => 'Approved',
                    'finalized' => 'Finalized',
                    'cancelled' => 'Cancelled',
                    'failed' => 'Failed',
                ]),
                Tables\Filters\SelectFilter::make('settlement_type')->options([
                    'subscription_pool' => 'Subscription pool',
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('calculate')
                    ->form([
                        Forms\Components\TextInput::make('period')
                            ->required()
                            ->placeholder(now()->subMonthNoOverflow()->format('Y-m'))
                            ->rules(['regex:/^\\d{4}-\\d{2}$/']),
                    ])
                    ->action(function (array $data): void {
                        app(CreatorSettlementService::class)->calculate($data['period']);
                        Notification::make()->title('Settlement calculated for review')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('recalculate')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (CreatorSettlement $record) => in_array($record->status, ['under_review', 'cancelled', 'failed'], true))
                    ->action(function (CreatorSettlement $record): void {
                        app(CreatorSettlementService::class)->calculate($record->period);
                        Notification::make()->title('Settlement recalculated for review')->success()->send();
                    }),
                Tables\Actions\Action::make('approve')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CreatorSettlement $record) => $record->status === 'under_review')
                    ->action(fn (CreatorSettlement $record) => app(CreatorSettlementService::class)->approve($record, auth()->user())),
                Tables\Actions\Action::make('finalize')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Finalized settlement entries are immutable. Corrections require adjustment entries.')
                    ->visible(fn (CreatorSettlement $record) => $record->status === 'approved')
                    ->action(fn (CreatorSettlement $record) => app(CreatorSettlementService::class)->finalize($record, auth()->user())),
                Tables\Actions\Action::make('cancel')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (CreatorSettlement $record) => in_array($record->status, ['draft', 'calculating', 'under_review'], true))
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(fn (CreatorSettlement $record, array $data) => app(CreatorSettlementService::class)
                        ->cancel($record, auth()->user(), $data['reason'])),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [AllocationsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreatorSettlements::route('/'),
            'view' => Pages\ViewCreatorSettlement::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
