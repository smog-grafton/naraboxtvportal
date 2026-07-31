<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorSupportRequestResource\Pages;
use App\Models\CreatorSupportRequest;
use App\Services\UserNotificationService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CreatorSupportRequestResource extends Resource
{
    protected static ?string $model = CreatorSupportRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationGroup = 'Creator Management';

    protected static ?string $navigationLabel = 'Creator Support';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->searchable(),
                Tables\Columns\TextColumn::make('category')->badge(),
                Tables\Columns\TextColumn::make('subject')->searchable()->limit(60),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('submission_id')->label('Submission')->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'in_progress' => 'In progress',
                    'resolved' => 'Resolved',
                    'closed' => 'Closed',
                ]),
                Tables\Filters\SelectFilter::make('category')
                    ->options(array_combine(config('creator.support_categories', []), config('creator.support_categories', []))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->form([
                        Forms\Components\Placeholder::make('creator_message')
                            ->content(fn (CreatorSupportRequest $record) => $record->message),
                        Forms\Components\Placeholder::make('admin_response')
                            ->content(fn (CreatorSupportRequest $record) => $record->admin_response ?: 'No response yet.'),
                    ]),
                Tables\Actions\Action::make('respond')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->form([
                        Forms\Components\Textarea::make('admin_response')->required()->rows(6),
                        Forms\Components\Select::make('status')
                            ->options(['in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'])
                            ->default('resolved')
                            ->required(),
                    ])
                    ->action(function (CreatorSupportRequest $record, array $data): void {
                        $record->update([
                            'admin_response' => $data['admin_response'],
                            'status' => $data['status'],
                            'responded_by' => auth()->id(),
                            'responded_at' => now(),
                        ]);
                        app(UserNotificationService::class)->createForUser($record->user_id, [
                            'title' => 'Creator support replied',
                            'message' => $data['admin_response'],
                            'type' => 'creator_support',
                            'action_url' => '/creator/support',
                        ]);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCreatorSupportRequests::route('/')];
    }
}
