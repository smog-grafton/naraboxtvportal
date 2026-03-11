<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VJResource\Pages;
use App\Models\VJ;
use App\Models\Genre;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class VJResource extends Resource
{
    protected static ?string $model = VJ::class;

    protected static ?string $navigationIcon = 'heroicon-o-microphone';
    protected static ?string $navigationLabel = 'VJs';
    protected static ?string $modelLabel = 'VJ';
    protected static ?string $pluralModelLabel = 'VJs';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Auto-generated from name'),
                        Forms\Components\Textarea::make('bio')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Media')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Profile Image')
                            ->image()
                            ->directory('vjs/profiles')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                            ])
                            ->maxSize(5120) // 5MB
                            ->required()
                            ->helperText('Upload a square profile image (recommended: 400x400px)')
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('banner')
                            ->label('Banner Image')
                            ->image()
                            ->directory('vjs/banners')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                            ])
                            ->maxSize(10240) // 10MB
                            ->helperText('Upload a banner image (recommended: 1920x1080px)')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Statistics')
                    ->schema([
                        Forms\Components\TextInput::make('rating')
                            ->numeric()
                            ->step(0.1)
                            ->minValue(0)
                            ->maxValue(5)
                            ->default(0)
                            ->required(),
                        Forms\Components\TextInput::make('translated_count')
                            ->label('Translated Count')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Featuring')
                    ->schema([
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured VJ')
                            ->helperText('Show this VJ in the Featured VJs section on the homepage')
                            ->default(false),
                        Forms\Components\TextInput::make('featured_order')
                            ->label('Featured Order')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Lower numbers appear first among featured VJs'),
                    ])->columns(2),

                Forms\Components\Section::make('Specialties')
                    ->schema([
                        Forms\Components\Select::make('genres')
                            ->multiple()
                            ->relationship('genres', 'name')
                            ->preload()
                            ->searchable()
                            ->label('Genre Specialties'),
                    ]),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Photo')
                    ->size(50)
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('rating')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state >= 4.5 ? 'success' : ($state >= 4 ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('translated_count')
                    ->label('Translations')
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('genres.name')
                    ->label('Specialties')
                    ->badge()
                    ->separator(','),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
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
            ->defaultSort('rating', 'desc');
    }

    /**
     * Enforce a maximum of 14 featured VJs.
     */
    public static function beforeSave(Model $record): void
    {
        if ($record->is_featured) {
            $count = VJ::where('is_featured', true)
                ->where('id', '!=', $record->id ?? 0)
                ->count();

            if ($count >= 14) {
                $record->is_featured = false;

                Notification::make()
                    ->title('Featured limit reached')
                    ->body('You can only feature up to 14 VJs on the homepage. Un-feature another VJ first.')
                    ->danger()
                    ->send();
            }
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVJS::route('/'),
            'create' => Pages\CreateVJ::route('/create'),
            'edit' => Pages\EditVJ::route('/{record}/edit'),
        ];
    }
}
