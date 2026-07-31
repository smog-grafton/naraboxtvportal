<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorProfileChangeRequestResource\Pages;
use App\Models\CreatorProfileChangeRequest;
use App\Services\CreatorAuditService;
use App\Services\UserNotificationService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CreatorProfileChangeRequestResource extends Resource
{
    protected static ?string $model = CreatorProfileChangeRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Creator Management';

    protected static ?string $navigationLabel = 'Profile Changes';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->searchable(),
                Tables\Columns\TextColumn::make('profile_type')->formatStateUsing(fn ($state) => class_basename($state))->badge(),
                Tables\Columns\TextColumn::make('profile.name')->label('Current name'),
                Tables\Columns\TextColumn::make('requested_changes.name')->label('Requested name'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'submitted' => 'Submitted',
                    'under_review' => 'Under review',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'superseded' => 'Superseded',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CreatorProfileChangeRequest $record) => in_array($record->status, ['submitted', 'under_review'], true))
                    ->action(function (CreatorProfileChangeRequest $record): void {
                        $profile = $record->profile;
                        abort_unless($profile, 404);
                        $old = ['name' => $profile->name];
                        $profile->update([
                            'name' => $record->requested_changes['name'] ?? $profile->name,
                            'profile_review_status' => 'approved',
                        ]);
                        $record->update([
                            'status' => 'approved',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        app(CreatorAuditService::class)->record(
                            'creator.profile_sensitive_change_approved',
                            auth()->user(),
                            $record->user,
                            $profile,
                            $old,
                            ['name' => $profile->name]
                        );
                        app(UserNotificationService::class)->createForUser($record->user_id, [
                            'title' => 'Creator profile change approved',
                            'message' => 'Your public creator display name has been approved.',
                            'type' => 'creator_profile',
                            'action_url' => '/creator/profile',
                        ]);
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('review_notes')->required(),
                    ])
                    ->visible(fn (CreatorProfileChangeRequest $record) => in_array($record->status, ['submitted', 'under_review'], true))
                    ->action(function (CreatorProfileChangeRequest $record, array $data): void {
                        $record->update([
                            'status' => 'rejected',
                            'review_notes' => $data['review_notes'],
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        app(UserNotificationService::class)->createForUser($record->user_id, [
                            'title' => 'Creator profile change needs attention',
                            'message' => 'Your requested display-name change was not approved. Review the administrator feedback.',
                            'type' => 'creator_profile',
                            'action_url' => '/creator/profile',
                        ]);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCreatorProfileChangeRequests::route('/')];
    }
}
