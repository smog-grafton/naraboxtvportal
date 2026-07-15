<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorApplicationResource\Pages;
use App\Models\CreatorApplication;
use App\Models\MediaLibrary;
use App\Models\Role;
use App\Models\VJ;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatorApplicationResource extends Resource
{
    protected static ?string $model = CreatorApplication::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $navigationLabel = 'Creator Applications';
    protected static ?string $modelLabel = 'Creator Application';
    protected static ?string $pluralModelLabel = 'Creator Applications';
    protected static ?string $navigationGroup = 'Creators';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Applicant')
                    ->schema([
                        Forms\Components\TextInput::make('user.name')
                            ->label('Applicant Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('user.email')
                            ->label('Email')
                            ->disabled(),
                        Forms\Components\Select::make('creator_type')
                            ->label('Creator Type')
                            ->options([
                                'vj' => 'VJ',
                                'media_library' => 'Media Library',
                            ])
                            ->disabled(),
                        Forms\Components\TextInput::make('display_name')
                            ->label('Display Name')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Application Details')
                    ->schema([
                        Forms\Components\Textarea::make('bio')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('genres')
                            ->label('Selected Genres (IDs)')
                            ->disabled()
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
                        Forms\Components\TextInput::make('profile_image')
                            ->label('Profile Image Path')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Review')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending'      => 'Pending',
                                'under_review' => 'Under Review',
                                'approved'     => 'Approved',
                                'rejected'     => 'Rejected',
                                'needs_changes' => 'Needs Changes',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection / Change Request Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Internal Admin Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Applicant')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('creator_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'vj',
                        'success' => 'media_library',
                    ])
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'vj' => 'VJ',
                        'media_library' => 'Media Library',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Display Name')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning'  => 'pending',
                        'info'     => 'under_review',
                        'success'  => 'approved',
                        'danger'   => 'rejected',
                        'gray'     => 'needs_changes',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'       => 'Pending',
                        'under_review'  => 'Under Review',
                        'approved'      => 'Approved',
                        'rejected'      => 'Rejected',
                        'needs_changes' => 'Needs Changes',
                    ]),
                Tables\Filters\SelectFilter::make('creator_type')
                    ->label('Creator Type')
                    ->options([
                        'vj'            => 'VJ',
                        'media_library' => 'Media Library',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Approving will create a creator profile and upgrade the user\'s role.')
                    ->visible(fn (CreatorApplication $record) => in_array($record->status, ['pending', 'under_review']))
                    ->action(function (CreatorApplication $record) {
                        static::approveApplication($record);
                        Notification::make()
                            ->title('Application approved')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (CreatorApplication $record) => in_array($record->status, ['pending', 'under_review']))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason for rejection')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (CreatorApplication $record, array $data, $livewire) {
                        $admin = auth()->user();
                        $record->reject($admin, $data['rejection_reason']);
                        Notification::make()
                            ->title('Application rejected')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\Action::make('request_changes')
                    ->label('Request Changes')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn (CreatorApplication $record) => in_array($record->status, ['pending', 'under_review']))
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Changes required')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (CreatorApplication $record, array $data) {
                        $admin = auth()->user();
                        $record->requestChanges($admin, $data['notes']);
                        Notification::make()
                            ->title('Changes requested')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\Action::make('mark_under_review')
                    ->label('Mark Under Review')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(fn (CreatorApplication $record) => $record->status === 'pending')
                    ->action(function (CreatorApplication $record) {
                        $record->markUnderReview();
                        Notification::make()
                            ->title('Marked as under review')
                            ->info()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function approveApplication(CreatorApplication $application): void
    {
        DB::transaction(function () use ($application) {
            $admin = auth()->user();
            $user = $application->user;

            // Get/create the appropriate role
            $roleName = $application->creator_type === 'vj' ? 'vj' : 'media_library';
            $role = Role::where('name', $roleName)->first();

            if ($application->creator_type === 'vj') {
                // Create or link VJ profile
                $vj = VJ::where('user_id', $user->id)->first()
                    ?? VJ::create([
                        'user_id'  => $user->id,
                        'name'     => $application->display_name,
                        'slug'     => Str::slug($application->display_name),
                        'bio'      => $application->bio,
                        'image'    => $application->profile_image ?? '',
                        'is_active' => true,
                    ]);
            } else {
                // Create or link Media Library profile
                MediaLibrary::where('user_id', $user->id)->first()
                    ?? MediaLibrary::create([
                        'user_id'     => $user->id,
                        'name'        => $application->display_name,
                        'slug'        => Str::slug($application->display_name),
                        'bio'         => $application->bio,
                        'image'       => $application->profile_image,
                        'is_active'   => true,
                        'is_verified' => true,
                    ]);
            }

            // Upgrade the user's role
            if ($role) {
                $user->update(['role_id' => $role->id]);
            }

            // Mark application as approved
            $application->approve($admin);
        });
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCreatorApplications::route('/'),
            'view'   => Pages\ViewCreatorApplication::route('/{record}'),
            'edit'   => Pages\EditCreatorApplication::route('/{record}/edit'),
        ];
    }
}
