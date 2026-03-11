<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MovieResource\Pages;
use App\Models\Movie;
use App\Models\Category;
use App\Models\VJ;
use App\Models\Genre;
use App\Models\Actor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class MovieResource extends Resource
{
    protected static ?string $model = Movie::class;

    protected static ?string $navigationIcon = 'heroicon-o-film';
    protected static ?string $navigationLabel = 'Movies';
    protected static ?string $modelLabel = 'Movie';
    protected static ?string $pluralModelLabel = 'Movies';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('TMDB Integration')
                    ->schema([
                        Forms\Components\TextInput::make('tmdb_search')
                            ->label('Search TMDB')
                            ->placeholder('Enter movie name to search')
                            ->live(onBlur: false)
                            ->debounce(500)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (strlen($state) >= 3) {
                                    $tmdbService = app(\App\Services\TmdbService::class);
                                    $results = $tmdbService->search($state, 'movie');
                                    $set('tmdb_search_results', $results['results'] ?? []);
                                } else {
                                    $set('tmdb_search_results', []);
                                }
                            })
                            ->helperText('Type at least 3 characters to search'),
                        Forms\Components\Select::make('tmdb_selected_result')
                            ->label('Select Movie')
                            ->options(function (Forms\Get $get) {
                                $results = $get('tmdb_search_results') ?? [];
                                $options = [];
                                foreach ($results as $result) {
                                    $releaseYear = isset($result['release_date']) ? date('Y', strtotime($result['release_date'])) : '';
                                    $options[$result['id']] = $result['title'] . ($releaseYear ? ' (' . $releaseYear . ')' : '');
                                }
                                return $options;
                            })
                            ->searchable(false)
                            ->live()
                            ->visible(fn (Forms\Get $get) => !empty($get('tmdb_search_results')))
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    MovieResource::populateMovieFromTmdb((int)$state, $set);
                                }
                            }),
                        Forms\Components\TextInput::make('tmdb_id')
                            ->label('TMDB ID (Direct)')
                            ->numeric()
                            ->helperText('Or enter TMDB ID directly (e.g., 798645)')
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state && is_numeric($state)) {
                                    MovieResource::populateMovieFromTmdb((int)$state, $set);
                                }
                            }),
                        Forms\Components\Hidden::make('tmdb_search_results'),
                        Forms\Components\Hidden::make('tmdb_formatted_data'),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->rows(3),
                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->required(),
                        Forms\Components\Hidden::make('media_type')
                            ->default('MOVIE'),
                    ])->columns(2),

                Forms\Components\Section::make('Media')
                    ->schema([
                        Forms\Components\FileUpload::make('thumbnail')
                            ->label('Thumbnail (Upload)')
                            ->image()
                            ->directory('thumbnails')
                            ->maxSize(5120) // 5MB
                            ->helperText('Upload thumbnail image file'),
                        Forms\Components\TextInput::make('thumbnail_url')
                            ->label('Thumbnail URL (Alternative)')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Or use URL instead of upload (e.g., from TMDB)'),
                        Forms\Components\FileUpload::make('backdrop')
                            ->label('Backdrop (Upload)')
                            ->image()
                            ->directory('backdrops')
                            ->maxSize(10240) // 10MB
                            ->helperText('Upload backdrop image file'),
                        Forms\Components\TextInput::make('backdrop_url')
                            ->label('Backdrop URL (Alternative)')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Or use URL instead of upload (e.g., from TMDB)'),
                        Forms\Components\TextInput::make('video_url')
                            ->label('Video URL (Fallback)')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Legacy fallback URL. Primary video sources are managed in Video Sources section below.'),
                        Forms\Components\TextInput::make('duration')
                            ->label('Duration')
                            ->maxLength(50)
                            ->helperText('e.g., 2h 15m, 45m'),
                    ])->columns(2),

                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\Select::make('vj_id')
                            ->label('VJ (Translator)')
                            ->relationship('vj', 'name')
                            ->searchable(),
                        Forms\Components\Select::make('genres')
                            ->multiple()
                            ->relationship('genres', 'name')
                            ->preload()
                            ->searchable(),
                        Forms\Components\TextInput::make('rating')
                            ->numeric()
                            ->step(0.1)
                            ->minValue(0)
                            ->maxValue(10),
                        Forms\Components\DatePicker::make('release_date')
                            ->required(),
                        Forms\Components\TextInput::make('trending_score')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Forms\Components\Section::make('Access & Pricing')
                    ->schema([
                        Forms\Components\Toggle::make('is_free')
                            ->label('Free Access')
                            ->helperText('Content can be accessed without account')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // If free is enabled, disable premium
                                if ($state) {
                                    $set('is_premium', false);
                                }
                            }),
                        Forms\Components\Toggle::make('is_premium')
                            ->label('Premium Access (Subscription Required)')
                            ->helperText('Content requires active subscription')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // If premium is enabled, disable free
                                if ($state) {
                                    $set('is_free', false);
                                }
                            }),
                        Forms\Components\TextInput::make('price_rent')
                            ->label('Rent Price (UGX)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Leave empty if rent is not available')
                            ->visible(fn (Forms\Get $get) => !$get('is_free')),
                        Forms\Components\TextInput::make('price_buy')
                            ->label('Buy Price (UGX)')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Leave empty if buy is not available')
                            ->visible(fn (Forms\Get $get) => !$get('is_free')),
                    ])->columns(2),

                Forms\Components\Section::make('Homepage Featuring')
                    ->schema([
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured on Home Page')
                            ->helperText('Show this movie in the Featured section on the homepage')
                            ->live(),
                        Forms\Components\TextInput::make('featured_order')
                            ->label('Featured Order')
                            ->numeric()
                            ->default(0)
                            ->visible(fn (Forms\Get $get) => $get('is_featured'))
                            ->helperText('Lower numbers appear first among featured movies'),
                    ])->columns(2),

                Forms\Components\Section::make('Views & Analytics')
                    ->schema([
                        Forms\Components\TextInput::make('manual_views')
                            ->label('Manual Views (Fake Views)')
                            ->helperText('Add fake views to boost trending score. Total views = views_count + manual_views')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        Forms\Components\Placeholder::make('views_count')
                            ->label('Actual Views Count')
                            ->content(fn ($record) => $record ? number_format($record->views_count ?? 0) : '0'),
                        Forms\Components\Placeholder::make('total_views')
                            ->label('Total Views (Actual + Manual)')
                            ->content(fn ($record) => $record ? number_format(($record->views_count ?? 0) + ($record->manual_views ?? 0)) : '0'),
                    ])->columns(3),

                Forms\Components\Section::make('Actors & Cast')
                    ->schema([
                        Forms\Components\Select::make('actors')
                            ->multiple()
                            ->relationship('actors', 'name')
                            ->preload()
                            ->searchable()
                            ->reactive()
                            ->helperText('Select actors/actresses for this movie/series')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\TextInput::make('image')->url(),
                                Forms\Components\TextInput::make('role'),
                                Forms\Components\Textarea::make('bio'),
                            ]),
                    ]),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\TextInput::make('certificate')
                            ->label('Certificate/Rating')
                            ->maxLength(255)
                            ->helperText('e.g., PG-13, R, 18+'),
                        Forms\Components\TextInput::make('country')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('original_language')
                            ->label('Original Language')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('language')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Download Settings')
                    ->schema([
                        Forms\Components\Toggle::make('download_enabled')
                            ->label('Enable Downloads')
                            ->default(true)
                            ->helperText('Allow users to download this movie'),
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
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->label('Poster')
                    ->size(50),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vj.name')
                    ->label('VJ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rating')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state >= 8 ? 'success' : ($state >= 6 ? 'warning' : 'danger')),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Hero')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_free')
                    ->label('Free Access'),
                Tables\Filters\TernaryFilter::make('is_premium')
                    ->label('Premium Access'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured in Hero'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Enforce a maximum of 14 featured movies.
     */
    public static function beforeSave(Model $record): void
    {
        if ($record->is_featured) {
            $count = Movie::where('is_featured', true)
                ->where('id', '!=', $record->id ?? 0)
                ->count();

            if ($count >= 14) {
                $record->is_featured = false;

                Notification::make()
                    ->title('Featured limit reached')
                    ->body('You can only feature up to 14 movies on the homepage. Un-feature another movie first.')
                    ->danger()
                    ->send();
            }
        }
    }

    protected static function populateMovieFromTmdb(int $tmdbId, Forms\Set $set): void
    {
        try {
            $tmdbService = app(\App\Services\TmdbService::class);
            $tmdbData = $tmdbService->getMovieDetails($tmdbId);
            
            if (!$tmdbData) {
                \Filament\Notifications\Notification::make()
                    ->warning()
                    ->title('TMDB Import Failed')
                    ->body('Could not fetch data from TMDB. Please check the TMDB ID.')
                    ->send();
                return;
            }

            $formattedData = $tmdbService->formatMovieData($tmdbData);
            
            // Download and save images to server
            $thumbnailPath = null;
            $backdropPath = null;
            
            if (!empty($formattedData['thumbnail'])) {
                $thumbnailPath = $tmdbService->downloadImage(
                    str_replace('https://image.tmdb.org/t/p/w500', '', parse_url($formattedData['thumbnail'], PHP_URL_PATH)),
                    'w500',
                    'poster'
                );
            }
            
            if (!empty($formattedData['backdrop'])) {
                $backdropPath = $tmdbService->downloadImage(
                    str_replace('https://image.tmdb.org/t/p/w1280', '', parse_url($formattedData['backdrop'], PHP_URL_PATH)),
                    'w1280',
                    'backdrop'
                );
            }
            
            // Populate all form fields
            $set('tmdb_id', $formattedData['tmdb_id']);
            $set('title', $formattedData['title']);
            $set('original_title', $formattedData['original_title']);
            $set('description', $formattedData['description']);
            $set('tagline', $formattedData['tagline']);
            
            // Set downloaded file paths for FileUpload fields
            if ($thumbnailPath) {
                $set('thumbnail', [$thumbnailPath]); // FileUpload expects array
            } else {
                $set('thumbnail_url', $formattedData['thumbnail']); // Fallback to URL
            }
            
            if ($backdropPath) {
                $set('backdrop', [$backdropPath]); // FileUpload expects array
            } else {
                $set('backdrop_url', $formattedData['backdrop']); // Fallback to URL
            }
            $set('rating', $formattedData['rating']);
            $set('release_date', $formattedData['release_date']);
            $set('duration', $formattedData['duration']);
            $set('original_language', $formattedData['original_language']);
            $set('certificate', $formattedData['certificate']);
            $set('country', $formattedData['country']);
            $set('language', $formattedData['language']);
            $set('budget', $formattedData['budget']);
            $set('revenue', $formattedData['revenue']);
            $set('status', $formattedData['status']);
            $set('homepage', $formattedData['homepage']);
            $set('popularity', $formattedData['popularity']);
            $set('vote_count', $formattedData['vote_count']);
            $set('imdb_id', $formattedData['imdb_id']);
            
            // Set genres
            $genreIds = [];
            foreach ($formattedData['genres'] as $genreName) {
                $genre = \App\Models\Genre::firstOrCreate(['name' => $genreName]);
                $genreIds[] = $genre->id;
            }
            $set('genres', $genreIds);
            
            // Download and import actor images immediately
            $actorIds = [];
            foreach ($formattedData['cast'] as $index => $actorData) {
                $actorImagePath = null;
                if (!empty($actorData['profile_path'])) {
                    // Extract TMDB path from full URL
                    $tmdbPath = parse_url($actorData['profile_path'], PHP_URL_PATH);
                    $tmdbPath = str_replace(['/t/p/w500', '/t/p/original'], '', $tmdbPath);
                    $tmdbPath = ltrim($tmdbPath, '/');
                    $actorImagePath = $tmdbService->downloadImage($tmdbPath, 'w500', 'actor');
                }
                
                $actor = \App\Models\Actor::firstOrCreate(
                    ['name' => $actorData['name']],
                    ['image' => $actorImagePath ?? $actorData['profile_path']]
                );
                
                // Update actor image if downloaded
                if ($actorImagePath && $actor->image !== $actorImagePath) {
                    $actor->update(['image' => $actorImagePath]);
                }
                
                // Store just the ID for the form field (Filament relationship field expects array of IDs)
                $actorIds[] = $actor->id;
            }
            // Set actors as array of IDs for Filament relationship field
            $set('actors', $actorIds);
            
            // Store formatted data for afterCreate
            $set('tmdb_formatted_data', $formattedData);
            
            \Filament\Notifications\Notification::make()
                ->success()
                ->title('TMDB Data Loaded')
                ->body('Movie data, images, and ' . count($actorIds) . ' actors have been downloaded and populated. Actors should appear in the Actors & Cast field.')
                ->send();
            
            // Force refresh actors field by setting again to ensure form field updates
            $set('actors', $actorIds);
                
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->danger()
                ->title('TMDB Import Error')
                ->body('Error: ' . $e->getMessage())
                ->send();
        }
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\MovieResource\RelationManagers\VideoSourcesRelationManager::class,
            \App\Filament\Resources\MovieResource\RelationManagers\DownloadSourcesRelationManager::class,
            \App\Filament\Resources\MovieResource\RelationManagers\SubtitlesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMovies::route('/'),
            'create' => Pages\CreateMovie::route('/create'),
            'view' => Pages\ViewMovie::route('/{record}'),
            'edit' => Pages\EditMovie::route('/{record}/edit'),
        ];
    }
}
