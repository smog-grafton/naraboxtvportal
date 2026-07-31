<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VJClaimRequestResource\Pages;
use App\Models\VJClaimRequest;
use App\Services\CreatorAuditService;
use App\Services\CreatorCommunicationService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VJClaimRequestResource extends Resource
{
    protected static ?string $model = VJClaimRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'VJ Claims';

    protected static ?string $modelLabel = 'VJ Claim Request';

    protected static ?string $pluralModelLabel = 'VJ Claim Requests';

    protected static ?string $navigationGroup = 'Creator Management';

    protected static ?int $navigationSort = 2;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Claim summary')
                ->description('Legacy claims are review records only. Creator ownership is granted exclusively through the evidence-based creator application.')
                ->schema([
                    Infolists\Components\TextEntry::make('user.name')->label('Applicant'),
                    Infolists\Components\TextEntry::make('user.email')->label('Account email')->copyable(),
                    Infolists\Components\TextEntry::make('vj.name')->label('Requested VJ profile'),
                    Infolists\Components\TextEntry::make('vj.slug')->label('Profile slug'),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    Infolists\Components\TextEntry::make('reviewer.name')->label('Reviewed by'),
                    Infolists\Components\TextEntry::make('reviewed_at')->dateTime(),
                    Infolists\Components\TextEntry::make('rejection_reason')
                        ->label('Creator-visible decision notes')
                        ->columnSpanFull(),
                ])->columns(2),
            Infolists\Components\Section::make('Secure verification')
                ->schema([
                    Infolists\Components\TextEntry::make('application_status')
                        ->label('Creator application')
                        ->getStateUsing(fn (VJClaimRequest $record) => $record->user?->creatorApplication?->status ?? 'Not started')
                        ->badge(),
                    Infolists\Components\TextEntry::make('evidence_status')
                        ->label('Evidence')
                        ->getStateUsing(fn (VJClaimRequest $record) => $record->user?->creatorApplication?->evidence()
                            ->where('status', 'submitted')
                            ->exists() ? 'Submitted' : 'Not submitted')
                        ->badge(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vj.name')
                    ->label('VJ')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Open claim'),
                Tables\Actions\Action::make('secure_review')
                    ->label('Open secure application')
                    ->icon('heroicon-o-shield-check')
                    ->color('warning')
                    ->url(function (VJClaimRequest $record): ?string {
                        $application = $record->user?->creatorApplication;

                        return $application
                            ? CreatorApplicationResource::getUrl('edit', ['record' => $application])
                            : null;
                    })
                    ->disabled(fn (VJClaimRequest $record): bool => ! $record->user?->creatorApplication)
                    ->tooltip('Legacy claims cannot grant ownership. Review the migrated evidence-based application instead.'),
                Tables\Actions\Action::make('start_review')
                    ->label('Start review')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (VJClaimRequest $record) => $record->status === 'pending' && ! $record->reviewed_by)
                    ->action(function (VJClaimRequest $record): void {
                        $record->update([
                            'reviewed_by' => auth()->id(),
                        ]);
                        app(CreatorAuditService::class)->record(
                            'creator.legacy_claim_review_started',
                            auth()->user(),
                            $record->user,
                            $record
                        );
                        app(CreatorCommunicationService::class)->notify(
                            $record->user,
                            "legacy-claim:{$record->id}:under-review",
                            'creator_claim_under_review',
                            'Creator profile claim under review',
                            "We have started reviewing your claim for {$record->vj?->name}.",
                            '/creator/verification',
                            ['creator_name' => $record->vj?->name]
                        );
                    }),
                Tables\Actions\Action::make('reject')
                    ->color('danger')
                    ->visible(fn (VJClaimRequest $record) => $record->status === 'pending')
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(function (VJClaimRequest $record, array $data): void {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['reason'],
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        app(CreatorAuditService::class)->record(
                            'creator.legacy_claim_rejected',
                            auth()->user(),
                            $record->user,
                            $record,
                            [],
                            ['reason' => $data['reason']]
                        );
                        app(CreatorCommunicationService::class)->notify(
                            $record->user,
                            "legacy-claim:{$record->id}:rejected",
                            'creator_claim_rejected',
                            'Creator profile claim update',
                            $data['reason'],
                            '/creator/verification',
                            ['creator_name' => $record->vj?->name]
                        );
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVJClaimRequests::route('/'),
            'view' => Pages\ViewVJClaimRequest::route('/{record}'),
        ];
    }
}
