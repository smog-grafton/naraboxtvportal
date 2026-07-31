<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorApplicationResource\Pages;
use App\Filament\Resources\CreatorApplicationResource\RelationManagers\ClaimsRelationManager;
use App\Filament\Resources\CreatorApplicationResource\RelationManagers\EvidenceRelationManager;
use App\Filament\Resources\CreatorApplicationResource\RelationManagers\InformationRequestsRelationManager;
use App\Models\CreatorApplication;
use App\Models\CreatorInformationRequest;
use App\Services\CreatorAuditService;
use App\Services\CreatorCommunicationService;
use App\Services\CreatorIdentityService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CreatorApplicationResource extends Resource
{
    protected static ?string $model = CreatorApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Creator Verification';

    protected static ?string $navigationGroup = 'Creators';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Applicant and public profile')
                ->schema([
                    Forms\Components\Placeholder::make('applicant_account_name')
                        ->label('Applicant account')
                        ->content(fn (?CreatorApplication $record) => $record?->user?->name ?? '—'),
                    Forms\Components\Placeholder::make('applicant_account_email')
                        ->label('Account email')
                        ->content(fn (?CreatorApplication $record) => $record?->user?->email ?? '—'),
                    Forms\Components\Select::make('creator_type')
                        ->options(['vj' => 'VJ / translator', 'media_library' => 'Media library / studio'])
                        ->disabled(),
                    Forms\Components\Select::make('application_mode')
                        ->options(['create' => 'Create profile', 'claim' => 'Claim existing profile'])
                        ->disabled(),
                    Forms\Components\TextInput::make('display_name')->disabled(),
                    Forms\Components\TextInput::make('legal_name')->disabled(),
                    Forms\Components\TextInput::make('phone_number')->disabled(),
                    Forms\Components\TextInput::make('public_email')->disabled(),
                    Forms\Components\TextInput::make('website_url')->disabled()->columnSpanFull(),
                    Forms\Components\Textarea::make('bio')->disabled()->columnSpanFull(),
                ])->columns(2),
            Forms\Components\Section::make('Verification state')
                ->schema([
                    Forms\Components\TextInput::make('status')->disabled(),
                    Forms\Components\TextInput::make('verification_status')->disabled(),
                    Forms\Components\Textarea::make('challenge_phrase')->disabled()->columnSpanFull(),
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Creator-visible decision notes')
                        ->disabled()
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Internal reviewer notes')
                        ->rows(4)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Applicant identity')
                ->schema([
                    Infolists\Components\TextEntry::make('user.name')->label('Applicant account'),
                    Infolists\Components\TextEntry::make('user.email')->label('Account email')->copyable(),
                    Infolists\Components\TextEntry::make('display_name')->label('Public creator name'),
                    Infolists\Components\TextEntry::make('legal_name')->label('Legal name'),
                    Infolists\Components\TextEntry::make('creator_type')->badge(),
                    Infolists\Components\TextEntry::make('application_mode')->badge(),
                    Infolists\Components\TextEntry::make('phone_number'),
                    Infolists\Components\TextEntry::make('public_email')->copyable(),
                    Infolists\Components\TextEntry::make('website_url')->url(fn ($state) => $state)->openUrlInNewTab(),
                    Infolists\Components\TextEntry::make('genres')->badge()->separator(','),
                    Infolists\Components\TextEntry::make('bio')->columnSpanFull(),
                    Infolists\Components\KeyValueEntry::make('social_links')->columnSpanFull(),
                ])->columns(2),
            Infolists\Components\Section::make('Verification review')
                ->schema([
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('verification_status')->badge(),
                    Infolists\Components\TextEntry::make('submitted_at')->dateTime(),
                    Infolists\Components\TextEntry::make('reviewed_at')->dateTime(),
                    Infolists\Components\TextEntry::make('reviewer.name')->label('Reviewer'),
                    Infolists\Components\TextEntry::make('challenge_expires_at')->dateTime(),
                    Infolists\Components\TextEntry::make('challenge_phrase')
                        ->label('Verification phrase')
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('rejection_reason')
                        ->label('Creator-visible decision notes')
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('admin_notes')
                        ->label('Internal reviewer notes')
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Applicant')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('display_name')->searchable(),
                Tables\Columns\TextColumn::make('creator_type')->badge(),
                Tables\Columns\TextColumn::make('application_mode')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'approved' => 'success',
                    'rejected', 'suspended', 'revoked' => 'danger',
                    'under_review' => 'info',
                    'additional_information_required' => 'warning',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('verification_status')->badge(),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'submitted' => 'Submitted',
                    'under_review' => 'Under review',
                    'additional_information_required' => 'Information required',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'suspended' => 'Suspended',
                    'revoked' => 'Revoked',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()->label('Reviewer notes'),
                Tables\Actions\Action::make('under_review')
                    ->label('Start review')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (CreatorApplication $record) => $record->status === 'submitted')
                    ->action(function (CreatorApplication $record): void {
                        $record->update(['status' => 'under_review']);
                        app(CreatorAuditService::class)->record(
                            'creator.application_review_started',
                            auth()->user(),
                            $record->user,
                            $record
                        );
                        app(CreatorCommunicationService::class)->notify(
                            $record->user,
                            "creator-application:{$record->id}:under-review",
                            'creator_application_under_review',
                            'Your creator application is under review',
                            'The creator team has started reviewing your application.',
                            '/creator/application',
                            [
                                'application_id' => $record->id,
                                'action_label' => 'View application',
                            ],
                            'creator_application_under_review'
                        );
                    }),
                Tables\Actions\Action::make('approve_identity')
                    ->label('Approve identity')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CreatorApplication $record) => in_array($record->status, ['submitted', 'under_review'], true))
                    ->form([
                        Forms\Components\Toggle::make('publishing_enabled')
                            ->helperText('Allows publishing after moderation. Identity approval alone does not require this.'),
                        Forms\Components\Toggle::make('monetization_enabled'),
                        Forms\Components\Toggle::make('withdrawals_enabled')
                            ->helperText('Only effective when monetization is also enabled.'),
                    ])
                    ->action(function (CreatorApplication $record, array $data): void {
                        app(CreatorIdentityService::class)->approve(
                            auth()->user(),
                            $record,
                            (bool) ($data['publishing_enabled'] ?? false),
                            (bool) ($data['monetization_enabled'] ?? false),
                            (bool) ($data['withdrawals_enabled'] ?? false)
                        );
                        Notification::make()->title('Creator identity approved')->success()->send();
                    }),
                Tables\Actions\Action::make('request_information')
                    ->label('Request information')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->visible(fn (CreatorApplication $record) => ! in_array($record->status, ['rejected', 'revoked'], true))
                    ->form([
                        Forms\Components\TextInput::make('field_label')->required()->maxLength(255),
                        Forms\Components\Select::make('field_type')->required()->options([
                            'short_text' => 'Short text',
                            'long_text' => 'Long text',
                            'number' => 'Number',
                            'phone' => 'Phone number',
                            'email' => 'Email',
                            'url' => 'URL',
                            'date' => 'Date',
                            'select' => 'Select',
                            'image' => 'Image',
                            'document' => 'Document/file',
                            'video' => 'Video',
                        ]),
                        Forms\Components\Select::make('step_key')
                            ->label('Application section')
                            ->required()
                            ->default('verification')
                            ->options([
                                'profile' => 'Profile details',
                                'selection' => 'Creator profile selection',
                                'ownership' => 'Ownership details',
                                'verification' => 'Verification',
                                'review' => 'Application review',
                            ])
                            ->helperText('The notification opens this exact part of the creator application.'),
                        Forms\Components\Textarea::make('instructions')->required()->columnSpanFull(),
                        Forms\Components\Toggle::make('is_required')->default(true),
                        Forms\Components\TagsInput::make('options')->helperText('Used by Select fields.'),
                        Forms\Components\TagsInput::make('allowed_mime_types')
                            ->helperText('Example: image/jpeg, application/pdf, video/mp4'),
                        Forms\Components\TextInput::make('max_file_size_kb')->numeric()->minValue(1),
                        Forms\Components\DateTimePicker::make('due_at'),
                        Forms\Components\Textarea::make('creator_message')->columnSpanFull(),
                        Forms\Components\Textarea::make('internal_reason')->required()->columnSpanFull(),
                    ])
                    ->action(function (CreatorApplication $record, array $data): void {
                        $informationRequest = CreatorInformationRequest::create(array_merge($data, [
                            'user_id' => $record->user_id,
                            'creator_application_id' => $record->id,
                            'creator_claim_id' => $record->claims()->latest()->value('id'),
                            'status' => 'open',
                            'created_by' => auth()->id(),
                        ]));
                        $previousStatus = $record->status;
                        $record->update([
                            'status' => 'additional_information_required',
                            'onboarding_step' => match ($informationRequest->step_key) {
                                'profile' => 2,
                                'selection', 'ownership' => 3,
                                'review' => 5,
                                default => 4,
                            },
                        ]);
                        app(CreatorAuditService::class)->record(
                            'creator.additional_information_requested',
                            auth()->user(),
                            $record->user,
                            $record,
                            ['status' => $previousStatus],
                            ['status' => 'additional_information_required'],
                            ['request_id' => $informationRequest->id, 'step_key' => $informationRequest->step_key]
                        );
                        $actionUrl = "/creator/application?request={$informationRequest->id}";
                        app(CreatorCommunicationService::class)->notify(
                            $record->user,
                            "creator-application:{$record->id}:information-request:{$informationRequest->id}",
                            'creator_application_information_requested',
                            'Creator verification needs information',
                            (string) ($data['creator_message'] ?: $data['instructions']),
                            $actionUrl,
                            [
                                'application_id' => $record->id,
                                'request_id' => $informationRequest->id,
                                'action_label' => 'Provide information',
                                'creator_name' => $record->display_name,
                            ],
                            'creator_additional_information_required'
                        );
                        Notification::make()->title('Information request sent')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->visible(fn (CreatorApplication $record) => ! in_array($record->status, ['approved', 'rejected', 'revoked'], true))
                    ->action(function (CreatorApplication $record, array $data): void {
                        $record->reject(auth()->user(), $data['reason']);
                        app(CreatorCommunicationService::class)->notify(
                            $record->user,
                            "creator-application:{$record->id}:rejected:".sha1($data['reason']),
                            'creator_application_rejected',
                            'Creator application update',
                            $data['reason'],
                            '/creator/application',
                            [
                                'application_id' => $record->id,
                                'action_label' => 'View application',
                            ],
                            'creator_application_rejected'
                        );
                    }),
                Tables\Actions\Action::make('suspend')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->visible(fn (CreatorApplication $record) => $record->status === 'approved')
                    ->action(function (CreatorApplication $record, array $data): void {
                        $record->update([
                            'status' => 'suspended',
                            'suspended_at' => now(),
                            'suspension_reason' => $data['reason'],
                        ]);
                        $record->permission?->update([
                            'is_suspended' => true,
                            'suspended_at' => now(),
                            'restriction_reason' => $data['reason'],
                        ]);
                        app(CreatorAuditService::class)->record(
                            'creator.application_suspended',
                            auth()->user(),
                            $record->user,
                            $record,
                            [],
                            ['reason' => $data['reason']]
                        );
                        app(CreatorCommunicationService::class)->notify(
                            $record->user,
                            "creator-application:{$record->id}:suspended:".sha1($data['reason']),
                            'creator_application_suspended',
                            'Creator account suspended',
                            $data['reason'],
                            '/creator/verification',
                            [],
                            'creator_application_suspended'
                        );
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ClaimsRelationManager::class,
            EvidenceRelationManager::class,
            InformationRequestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreatorApplications::route('/'),
            'view' => Pages\ViewCreatorApplication::route('/{record}'),
            'edit' => Pages\EditCreatorApplication::route('/{record}/edit'),
        ];
    }
}
