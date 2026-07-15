<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaLibraryResource\Pages;
use App\Models\MediaLibrary;
use Filament\Forms;
use Filament\Forms\Form;
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
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name ?? '')),
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
            //
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
