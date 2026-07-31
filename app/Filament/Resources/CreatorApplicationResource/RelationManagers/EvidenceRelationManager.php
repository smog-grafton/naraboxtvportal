<?php

namespace App\Filament\Resources\CreatorApplicationResource\RelationManagers;

use App\Models\CreatorVerificationEvidence;
use App\Services\CreatorAuditService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EvidenceRelationManager extends RelationManager
{
    protected static string $relationship = 'evidence';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('kind')->badge(),
                Tables\Columns\TextColumn::make('original_filename')->searchable(),
                Tables\Columns\TextColumn::make('mime_type'),
                Tables\Columns\TextColumn::make('size_bytes')->formatStateUsing(fn ($state) => number_format(((int) $state) / 1048576, 2).' MB'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('uploaded_at')->dateTime(),
                Tables\Columns\TextColumn::make('retention_until')->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options(['accepted' => 'Accepted', 'rejected' => 'Rejected'])
                            ->required(),
                    ])
                    ->action(function (CreatorVerificationEvidence $record, array $data): void {
                        $record->update([
                            'status' => $data['status'],
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        app(CreatorAuditService::class)->record(
                            'creator.evidence_reviewed',
                            auth()->user(),
                            $record->user,
                            $record,
                            [],
                            ['status' => $data['status']]
                        );
                    }),
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (CreatorVerificationEvidence $record) => route('creator.evidence.download', ['evidence' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([]);
    }
}
