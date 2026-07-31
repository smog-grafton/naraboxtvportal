<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaLibraryResource\Pages;
use App\Models\MediaLibrary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MediaLibraryResource extends Resource
{
    protected static ?string $model = MediaLibrary::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Media Libraries';

    protected static ?string $modelLabel = 'Media Library';

    protected static ?string $pluralModelLabel = 'Media Libraries';

    protected static ?string $navigationGroup = 'Creator Management';

    protected static ?int $navigationSort = 3;

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Library identity')
                ->schema([
                    Infolists\Components\ImageEntry::make('image')->circular(),
                    Infolists\Components\TextEntry::make('name'),
                    Infolists\Components\TextEntry::make('slug')->copyable(),
                    Infolists\Components\TextEntry::make('location'),
                    Infolists\Components\TextEntry::make('public_email')->copyable(),
                    Infolists\Components\TextEntry::make('languages')->badge()->separator(','),
                    Infolists\Components\TextEntry::make('bio')->columnSpanFull(),
                    Infolists\Components\KeyValueEntry::make('official_links')->columnSpanFull(),
                ])->columns(2),
            Infolists\Components\Section::make('Creator account and readiness')
                ->schema([
                    Infolists\Components\TextEntry::make('user.name')->label('Linked account'),
                    Infolists\Components\TextEntry::make('user.email')->label('Account email')->copyable(),
                    Infolists\Components\TextEntry::make('application_status')
                        ->label('Creator application')
                        ->getStateUsing(fn (MediaLibrary $record) => $record->user?->creatorApplication?->status ?? 'Not linked')
                        ->badge(),
                    Infolists\Components\TextEntry::make('wallet_status')
                        ->label('Wallet')
                        ->getStateUsing(fn (MediaLibrary $record) => $record->user?->creatorWallet?->status ?? 'Not created')
                        ->badge(),
                    Infolists\Components\IconEntry::make('is_active')->boolean(),
                    Infolists\Components\IconEntry::make('is_verified')->boolean(),
                    Infolists\Components\IconEntry::make('is_featured')->boolean(),
                ])->columns(2),
        ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Auto-generated from name'),
                        Forms\Components\Select::make('user_id')
                            ->label('Linked User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Optional: Link to a user account'),
                        Forms\Components\Textarea::make('bio')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\TagsInput::make('languages'),
                        Forms\Components\TextInput::make('location')->maxLength(255),
                        Forms\Components\TextInput::make('public_email')->email()->maxLength(255),
                        Forms\Components\KeyValue::make('official_links')
                            ->keyLabel('Network')
                            ->valueLabel('Public URL')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Media')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Profile Image / Logo')
                            ->image()
                            ->directory('media-libraries/profiles')
                            ->imageEditor()
                            ->imageEditorAspectRatios(['1:1'])
                            ->maxSize(5120)
                            ->helperText('Recommended: 400x400px'),
                        Forms\Components\FileUpload::make('banner')
                            ->label('Banner Image')
                            ->image()
                            ->directory('media-libraries/banners')
                            ->imageEditor()
                            ->imageEditorAspectRatios(['16:9'])
                            ->maxSize(10240)
                            ->helperText('Recommended: 1920x1080px'),
                    ])->columns(2),

                Forms\Components\Section::make('Visibility & Featuring')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive libraries are hidden from public'),
                        Forms\Components\Toggle::make('is_verified')
                            ->label('Verified')
                            ->default(false)
                            ->helperText('Show verified badge'),
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured')
                            ->default(false)
                            ->helperText('Show in featured section'),
                        Forms\Components\TextInput::make('featured_order')
                            ->label('Featured Order')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Lower numbers appear first'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Photo')
                    ->size(50)
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name ?? '')),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Account email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('application_status')
                    ->label('Application')
                    ->getStateUsing(fn (MediaLibrary $record) => $record->user?->creatorApplication?->status ?? 'not_linked')
                    ->badge(),
                Tables\Columns\TextColumn::make('wallet_status')
                    ->label('Wallet')
                    ->getStateUsing(fn (MediaLibrary $record) => $record->user?->creatorWallet?->status ?? 'not_created')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('movies_count')
                    ->label('Movies')
                    ->counts('movies')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('tv_shows_count')
                    ->label('TV Shows')
                    ->counts('tvShows')
                    ->badge()
                    ->color('success'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Verified'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
                Tables\Filters\Filter::make('linked_creator')
                    ->label('Has linked creator')
                    ->query(fn ($query) => $query->whereNotNull('user_id')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\VJResource\RelationManagers\MoviesRelationManager::class,
            \App\Filament\Resources\VJResource\RelationManagers\TVShowsRelationManager::class,
            \App\Filament\Resources\VJResource\RelationManagers\ClaimsRelationManager::class,
            \App\Filament\Resources\VJResource\RelationManagers\OwnershipHistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaLibraries::route('/'),
            'create' => Pages\CreateMediaLibrary::route('/create'),
            'view' => Pages\ViewMediaLibrary::route('/{record}'),
            'edit' => Pages\EditMediaLibrary::route('/{record}/edit'),
        ];
    }
}
