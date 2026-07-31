<?php

namespace App\Filament\Resources\CreatorApplicationResource\RelationManagers;

use App\Models\CreatorInformationRequest;
use App\Services\CreatorAuditService;
use App\Services\CreatorCommunicationService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InformationRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'informationRequests';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('field_label')->searchable(),
                Tables\Columns\TextColumn::make('field_type')->badge(),
                Tables\Columns\IconColumn::make('is_required')->boolean(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('due_at')->dateTime(),
                Tables\Columns\TextColumn::make('responses_count')->counts('responses')->label('Responses'),
                Tables\Columns\TextColumn::make('latest_response')
                    ->label('Latest response')
                    ->state(fn (CreatorInformationRequest $record) => $record->responses()->latest()->value('response_text'))
                    ->limit(45)
                    ->placeholder(fn (CreatorInformationRequest $record) => $record->responses()->latest()->value('evidence_id') ? 'Private file attached' : '—'),
                Tables\Columns\TextColumn::make('response_status')
                    ->badge()
                    ->state(fn (CreatorInformationRequest $record) => $record->responses()->latest()->value('status'))
                    ->placeholder('Waiting'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('accept_response')
                    ->label('Accept')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CreatorInformationRequest $record) => $record->status === 'submitted'
                        && $record->responses()->where('status', 'submitted')->exists())
                    ->action(function (CreatorInformationRequest $record): void {
                        $response = $record->responses()->where('status', 'submitted')->latest()->firstOrFail();
                        $response->update([
                            'status' => 'accepted',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        $record->update([
                            'status' => 'accepted',
                            'closed_by' => auth()->id(),
                            'closed_at' => now(),
                        ]);
                        app(CreatorAuditService::class)->record(
                            'creator.additional_information_accepted',
                            auth()->user(),
                            $record->user,
                            $record,
                            ['status' => 'submitted'],
                            ['status' => 'accepted'],
                            ['response_id' => $response->id]
                        );
                    }),
                Tables\Actions\Action::make('request_response_changes')
                    ->label('Request changes')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        Forms\Components\Textarea::make('feedback')
                            ->label('What should the creator change?')
                            ->required(),
                    ])
                    ->visible(fn (CreatorInformationRequest $record) => in_array($record->status, ['submitted', 'accepted'], true)
                        && $record->responses()->whereIn('status', ['submitted', 'accepted'])->exists())
                    ->action(function (CreatorInformationRequest $record, array $data): void {
                        $response = $record->responses()->whereIn('status', ['submitted', 'accepted'])->latest()->firstOrFail();
                        $response->update([
                            'status' => 'needs_changes',
                            'review_feedback' => $data['feedback'],
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        $record->update([
                            'status' => 'needs_changes',
                            'creator_message' => $data['feedback'],
                            'closed_by' => null,
                            'closed_at' => null,
                        ]);
                        $application = $this->getOwnerRecord();
                        $previousStatus = $application->status;
                        $application->update([
                            'status' => 'additional_information_required',
                            'onboarding_step' => match ($record->step_key) {
                                'profile' => 2,
                                'selection', 'ownership' => 3,
                                'review' => 5,
                                default => 4,
                            },
                        ]);
                        app(CreatorAuditService::class)->record(
                            'creator.additional_information_changes_requested',
                            auth()->user(),
                            $record->user,
                            $record,
                            ['status' => $previousStatus],
                            ['status' => 'additional_information_required'],
                            ['response_id' => $response->id]
                        );
                        app(CreatorCommunicationService::class)->notify(
                            $record->user,
                            "creator-application:{$application->id}:information-request:{$record->id}:changes:{$response->reviewed_at?->timestamp}",
                            'creator_application_information_requested',
                            'Your creator information needs an update',
                            (string) $data['feedback'],
                            "/creator/application?request={$record->id}",
                            [
                                'application_id' => $application->id,
                                'request_id' => $record->id,
                                'action_label' => 'Update response',
                                'creator_name' => $application->display_name,
                            ],
                            'creator_additional_information_required'
                        );
                    }),
                Tables\Actions\Action::make('waive')
                    ->label('Waive request')
                    ->requiresConfirmation()
                    ->visible(fn (CreatorInformationRequest $record) => in_array($record->status, ['open', 'needs_changes'], true))
                    ->action(function (CreatorInformationRequest $record): void {
                        $record->update([
                            'status' => 'waived',
                            'closed_by' => auth()->id(),
                            'closed_at' => now(),
                        ]);
                        $application = $this->getOwnerRecord();
                        $hasOutstandingRequiredRequest = $application->informationRequests()
                            ->where('is_required', true)
                            ->whereIn('status', ['open', 'needs_changes'])
                            ->exists();
                        if (! $hasOutstandingRequiredRequest
                            && in_array($application->status, ['additional_information_required', 'needs_changes'], true)) {
                            $application->update(['status' => 'under_review']);
                        }
                        app(CreatorAuditService::class)->record(
                            'creator.additional_information_waived',
                            auth()->user(),
                            $record->user,
                            $record,
                            ['status' => 'open'],
                            ['status' => 'waived']
                        );
                    }),
            ])
            ->headerActions([]);
    }
}
