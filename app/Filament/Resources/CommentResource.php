<?php

namespace App\Filament\Resources;

use App\Events\CommentReplyCreated;
use App\Filament\Resources\CommentResource\Pages;
use App\Models\Comment;
use App\Models\Movie;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Comments';
    protected static ?string $modelLabel = 'Comment';
    protected static ?string $pluralModelLabel = 'Comments';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Comment Information')
                    ->schema([
                        Forms\Components\Select::make('media_id')
                            ->label('Media (Movie/TV Show)')
                            ->relationship('media', 'title')
                            ->searchable()
                            ->required()
                            ->preload(),
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\TextInput::make('user_name')
                            ->label('User Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Required if user is not selected'),
                        Forms\Components\TextInput::make('avatar')
                            ->label('Avatar URL')
                            ->url()
                            ->maxLength(255)
                            ->helperText('User avatar image URL'),
                        Forms\Components\Textarea::make('text')
                            ->label('Comment Text')
                            ->required()
                            ->rows(4)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Comment (Reply To)')
                            ->relationship('parent', 'text', fn (Builder $query) => $query->whereNull('parent_id'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Leave empty for top-level comment'),
                        Forms\Components\TextInput::make('likes')
                            ->label('Likes')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->size(50)
                    ->circular(),
                Tables\Columns\TextColumn::make('user_name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('media.title')
                    ->label('Media')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('text')
                    ->label('Comment')
                    ->limit(50)
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('parent_id')
                    ->label('Is Reply')
                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No')
                    ->badge()
                    ->color(fn ($state) => $state ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('replies_count')
                    ->counts('replies')
                    ->label('Replies')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('parent.user_name')
                    ->label('Replying To')
                    ->placeholder('Top-level')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('likes')
                    ->label('Likes')
                    ->sortable()
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('media_id')
                    ->label('Media')
                    ->relationship('media', 'title')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('parent_id')
                    ->label('Comment Type')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->options([
                                'top_level' => 'Top Level Comments',
                                'replies' => 'Replies Only',
                            ])
                            ->default('top_level'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['type'] === 'top_level') {
                            return $query->whereNull('parent_id');
                        } elseif ($data['type'] === 'replies') {
                            return $query->whereNotNull('parent_id');
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('reply')
                    ->label('Reply')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('primary')
                    ->form([
                        Forms\Components\Textarea::make('text')
                            ->label('Reply')
                            ->required()
                            ->rows(4)
                            ->maxLength(5000),
                    ])
                    ->action(function (Comment $record, array $data): void {
                        $user = Auth::user();
                        $replyTarget = $record->parent_id ? $record->parent : $record;
                        $displayName = is_object($user) ? (string) ($user->name ?? 'Admin') : 'Admin';

                        $reply = Comment::create([
                            'media_id' => (int) $replyTarget->media_id,
                            'user_id' => is_object($user) && isset($user->id) ? (int) $user->id : null,
                            'user_name' => $displayName,
                            'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($displayName),
                            'text' => (string) $data['text'],
                            'parent_id' => (int) $replyTarget->id,
                            'likes' => 0,
                        ]);

                        event(new CommentReplyCreated($reply));

                        Notification::make()
                            ->title('Reply posted')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('view_replies')
                    ->label('View Replies')
                    ->icon('heroicon-o-arrow-right')
                    ->url(fn (Comment $record) => static::getUrl('index', ['tableFilters' => ['parent_id' => ['value' => $record->id]]]))
                    ->visible(fn (Comment $record) => $record->replies()->count() > 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComments::route('/'),
            'create' => Pages\CreateComment::route('/create'),
            'edit' => Pages\EditComment::route('/{record}/edit'),
        ];
    }
}
