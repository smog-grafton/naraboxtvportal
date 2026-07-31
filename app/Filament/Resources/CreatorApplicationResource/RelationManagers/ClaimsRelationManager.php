<?php

namespace App\Filament\Resources\CreatorApplicationResource\RelationManagers;

use Filament\Infolists;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ClaimsRelationManager extends RelationManager
{
    protected static string $relationship = 'claims';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('creator_name')->searchable(),
                Tables\Columns\TextColumn::make('claimable_type')
                    ->formatStateUsing(fn (string $state) => class_basename($state)),
                Tables\Columns\TextColumn::make('relationship_explanation')->limit(80)->wrap(),
                Tables\Columns\TextColumn::make('verification_method')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('evidence_count')->counts('evidence')->label('Evidence'),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->infolist([
                        Infolists\Components\Section::make('Ownership claim')
                            ->schema([
                                Infolists\Components\TextEntry::make('creator_name')->label('Claimed creator'),
                                Infolists\Components\TextEntry::make('claimable_type')
                                    ->formatStateUsing(fn ($state) => class_basename((string) $state)),
                                Infolists\Components\TextEntry::make('status')->badge(),
                                Infolists\Components\TextEntry::make('verification_method')->badge(),
                                Infolists\Components\TextEntry::make('legal_name'),
                                Infolists\Components\TextEntry::make('email')->copyable(),
                                Infolists\Components\TextEntry::make('phone_number'),
                                Infolists\Components\TextEntry::make('official_social_url')->url(fn ($state) => $state)->openUrlInNewTab(),
                                Infolists\Components\TextEntry::make('telegram_url')->url(fn ($state) => $state)->openUrlInNewTab(),
                                Infolists\Components\TextEntry::make('youtube_url')->url(fn ($state) => $state)->openUrlInNewTab(),
                                Infolists\Components\TextEntry::make('sample_work_url')->url(fn ($state) => $state)->openUrlInNewTab(),
                                Infolists\Components\TextEntry::make('existing_business_contact'),
                                Infolists\Components\TextEntry::make('relationship_explanation')->columnSpanFull(),
                                Infolists\Components\TextEntry::make('challenge_phrase')->columnSpanFull(),
                                Infolists\Components\TextEntry::make('review_notes')->columnSpanFull(),
                                Infolists\Components\TextEntry::make('rejection_reason')->columnSpanFull(),
                            ])->columns(2),
                    ]),
            ])
            ->headerActions([]);
    }
}
