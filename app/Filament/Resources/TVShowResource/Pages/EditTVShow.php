<?php

namespace App\Filament\Resources\TVShowResource\Pages;

use App\Events\ShowPublished;
use App\Filament\Resources\TVShowResource;
use App\Services\CampaignService;
use App\Models\PushNotification;
use App\Services\PushNotificationService;
use App\Services\TmdbService;
use App\Models\TVShow;
use App\Models\Movie;
use App\Models\Genre;
use App\Models\Actor;
use App\Models\Season;
use App\Models\Episode;
use App\Models\Crew;
use App\Models\Trailer;
use App\Models\Keyword;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class EditTVShow extends EditRecord
{
    protected static string $resource = TVShowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_tmdb')
                ->label('Import from TMDB')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Import from TMDB')
                ->modalDescription('This will fetch and import all metadata, seasons, and episodes from TMDB. Existing data may be overwritten.')
                ->form([
                    \Filament\Forms\Components\TextInput::make('tmdb_id')
                        ->label('TMDB ID')
                        ->numeric()
                        ->required()
                        ->helperText('Enter the TMDB ID of the TV show (e.g., 66732 for Stranger Things)'),
                ])
                ->action(function (array $data) {
                    $this->importTmdbData($data['tmdb_id']);
                }),
            Actions\Action::make('import_seasons_episodes')
                ->label('Import Seasons & Episodes')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Import Seasons & Episodes')
                ->modalDescription('This will import all seasons and episodes from TMDB. This may take a few minutes for shows with many seasons.')
                ->visible(fn () => $this->record->tmdb_id)
                ->action(function () {
                    $this->importSeasonsAndEpisodes();
                }),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure primary category_id is in categories pivot
        if (!empty($data['category_id'])) {
            $data['categories'] = $data['categories'] ?? [];
            if (!in_array($data['category_id'], $data['categories'])) {
                $data['categories'][] = $data['category_id'];
            }
        }
        return $data;
    }

    protected function afterSave(): void
    {
        $tvShow = $this->record;
        $formState = $this->form->getState();

        if (!empty($formState['send_push_on_save'])) {
            $title = $formState['push_title'] ?: $tvShow->title;
            $body = $formState['push_body'] ?: 'Updated TV show: ' . $tvShow->title;

            $notification = PushNotification::create([
                'title' => $title,
                'body' => $body,
                'image_url' => $formState['push_image_url'] ?? null,
                'deep_link' => 'app://tv-show/' . $tvShow->id,
                'target_platform' => $formState['push_target_platform'] ?? 'all',
                'target_audience' => 'all',
                'provider' => 'default',
                'notification_type' => 'marketing',
                'status' => 'queued',
            ]);

            PushNotificationService::send($notification);
        }

        if ($tvShow->is_active && ($tvShow->content_status ?? 'published') === 'published') {
            event(new ShowPublished($tvShow));
        }

        if (!empty($formState['send_email_on_save']) && $tvShow->is_active && ($tvShow->content_status ?? 'published') === 'published') {
            app(CampaignService::class)->queueTemplateCampaign(
                name: $formState['email_subject_override'] ?: ('TV show update: ' . $tvShow->title),
                templateName: 'new_show_added',
                templateData: [
                    'show_title' => $tvShow->title,
                    'watch_url' => rtrim((string) config('app.url'), '/') . '/tv-shows/' . $tvShow->id,
                    'created_at' => now(),
                ],
                audienceType: $formState['email_audience_type'] ?? 'selected_users',
                sendToAll: ($formState['email_audience_type'] ?? 'selected_users') === 'selected_users',
                marketingOnly: (bool) ($formState['email_marketing_only'] ?? true),
            );
        }
    }

    private function importTmdbData(int $tmdbId): void
    {
        try {
            $tvShow = $this->record;
            $tmdbService = app(TmdbService::class);
            $tmdbData = $tmdbService->getTvShowDetails($tmdbId);

            if (!$tmdbData) {
                Notification::make()
                    ->warning()
                    ->title('TMDB Import Failed')
                    ->body('Could not fetch data from TMDB. Please check the TMDB ID.')
                    ->send();
                return;
            }

            $formattedData = $tmdbService->formatTvShowData($tmdbData);

            // Download and save images
            $thumbnailPath = null;
            $backdropPath = null;
            
            if (!empty($formattedData['thumbnail'])) {
                // Extract TMDB path from full URL
                $tmdbPath = parse_url($formattedData['thumbnail'], PHP_URL_PATH);
                $tmdbPath = str_replace(['/t/p/w500', '/t/p/original'], '', $tmdbPath);
                $tmdbPath = ltrim($tmdbPath, '/');
                $thumbnailPath = $tmdbService->downloadImage($tmdbPath, 'w500', 'poster');
            }
            
            if (!empty($formattedData['backdrop'])) {
                // Extract TMDB path from full URL
                $tmdbPath = parse_url($formattedData['backdrop'], PHP_URL_PATH);
                $tmdbPath = str_replace(['/t/p/w1280', '/t/p/original'], '', $tmdbPath);
                $tmdbPath = ltrim($tmdbPath, '/');
                $backdropPath = $tmdbService->downloadImage($tmdbPath, 'w1280', 'backdrop');
            }

            DB::transaction(function () use ($tvShow, $formattedData, $tmdbService, $tmdbData, $thumbnailPath, $backdropPath) {
                // Update TV show with TMDB data
                $tvShow->update([
                    'tmdb_id' => $formattedData['tmdb_id'],
                    'imdb_id' => $formattedData['imdb_id'],
                    'title' => $formattedData['title'],
                    'original_title' => $formattedData['original_title'],
                    'description' => $formattedData['description'],
                    'tagline' => $formattedData['tagline'],
                    // Use downloaded file paths or fallback to URLs
                    'thumbnail' => $thumbnailPath ?? $formattedData['thumbnail'] ?? null,
                    'backdrop' => $backdropPath ?? $formattedData['backdrop'] ?? null,
                    'rating' => $formattedData['rating'],
                    'release_date' => $formattedData['release_date'],
                    'duration' => $formattedData['duration'],
                    'status' => $formattedData['status'],
                    'homepage' => $formattedData['homepage'],
                    'popularity' => $formattedData['popularity'],
                    'vote_count' => $formattedData['vote_count'],
                    'number_of_seasons' => $formattedData['number_of_seasons'],
                    'number_of_episodes' => $formattedData['number_of_episodes'],
                    'original_language' => $formattedData['original_language'],
                    'certificate' => $formattedData['certificate'],
                    'country' => $formattedData['country'],
                    'language' => $formattedData['language'],
                    'networks' => $formattedData['networks'],
                    'production_companies' => $formattedData['production_companies'],
                    'production_countries' => $formattedData['production_countries'],
                ]);

                // Sync genres
                $genreIds = [];
                foreach ($formattedData['genres'] as $genreName) {
                    $genre = Genre::firstOrCreate(['name' => $genreName]);
                    $genreIds[] = $genre->id;
                }
                $tvShow->genres()->sync($genreIds);

                // Sync actors - download and save images
                $tvShow->actors()->detach();
                foreach ($formattedData['cast'] as $index => $actorData) {
                    $actorImagePath = null;
                    if (!empty($actorData['profile_path'])) {
                        // profile_path is already the raw TMDB path (e.g., "/abc123.jpg")
                        $tmdbPath = ltrim($actorData['profile_path'], '/');
                        $actorImagePath = $tmdbService->downloadImage($tmdbPath, 'w500', 'actor');
                    }
                    
                    $actor = Actor::firstOrCreate(
                        ['name' => $actorData['name']],
                        ['image' => $actorImagePath ?? $actorData['profile_path'] ?? null]
                    );
                    
                    // Update actor image if downloaded and different
                    if ($actorImagePath && $actor->image !== $actorImagePath) {
                        $actor->update(['image' => $actorImagePath]);
                    }
                    
                    $tvShow->actors()->attach($actor->id, [
                        'role' => $actorData['character'],
                        'order' => $actorData['order'],
                    ]);
                }

                // Create crew members (directors, writers, producers)
                $tvShow->crew()->delete();
                
                // Directors
                foreach ($formattedData['crew']['directors'] as $director) {
                    Crew::create([
                        'crewable_type' => TVShow::class,
                        'crewable_id' => $tvShow->id,
                        'tmdb_id' => $director['tmdb_id'],
                        'name' => $director['name'],
                        'job' => 'Director',
                        'department' => 'Directing',
                        'profile_image' => $director['profile_path'],
                        'order' => 0,
                    ]);
                }
                
                // Writers
                foreach ($formattedData['crew']['writers'] as $writer) {
                    Crew::create([
                        'crewable_type' => TVShow::class,
                        'crewable_id' => $tvShow->id,
                        'tmdb_id' => $writer['tmdb_id'],
                        'name' => $writer['name'],
                        'job' => $writer['job'],
                        'department' => 'Writing',
                        'profile_image' => $writer['profile_path'],
                        'order' => 0,
                    ]);
                }
                
                // Producers
                foreach ($formattedData['crew']['producers'] as $producer) {
                    Crew::create([
                        'crewable_type' => TVShow::class,
                        'crewable_id' => $tvShow->id,
                        'tmdb_id' => $producer['tmdb_id'],
                        'name' => $producer['name'],
                        'job' => 'Producer',
                        'department' => 'Production',
                        'profile_image' => $producer['profile_path'],
                        'order' => 0,
                    ]);
                }

                // Create trailers
                $tvShow->trailers()->delete();
                foreach ($formattedData['trailers'] as $trailer) {
                    Trailer::create([
                        'trailerable_type' => TVShow::class,
                        'trailerable_id' => $tvShow->id,
                        'tmdb_id' => $trailer['tmdb_id'],
                        'key' => $trailer['key'],
                        'name' => $trailer['name'],
                        'site' => $trailer['site'],
                        'type' => $trailer['type'],
                        'size' => $trailer['size'],
                        'is_primary' => true,
                        'is_active' => true,
                    ]);
                }

                // Create keywords
                $tvShow->keywords()->delete();
                foreach ($formattedData['keywords'] as $keywordName) {
                    Keyword::create([
                        'keywordable_type' => TVShow::class,
                        'keywordable_id' => $tvShow->id,
                        'name' => $keywordName,
                    ]);
                }

                // Import seasons and episodes
                // Find or create corresponding movie entry for this TV show
                $movie = Movie::where('media_type', 'SERIES')
                    ->where('title', $tvShow->title)
                    ->first();
                
                // If no movie entry exists, create one
                if (!$movie) {
                    $movie = Movie::create([
                        'title' => $tvShow->title,
                        'slug' => $tvShow->slug,
                        'description' => $tvShow->description,
                        'thumbnail' => $tvShow->thumbnail,
                        'backdrop' => $tvShow->backdrop,
                        'rating' => $tvShow->rating,
                        'release_date' => $tvShow->release_date,
                        'category_id' => $tvShow->category_id,
                        'vj_id' => $tvShow->vj_id,
                        'media_type' => 'SERIES',
                        'access_type' => $tvShow->access_type,
                        'is_free' => $tvShow->is_free,
                        'is_premium' => $tvShow->is_premium,
                        'price_rent' => $tvShow->price_rent,
                        'price_buy' => $tvShow->price_buy,
                        'is_active' => $tvShow->is_active,
                    ]);
                }
                
                foreach ($formattedData['seasons'] as $seasonData) {
                    if ($seasonData['season_number'] == 0) continue; // Skip specials
                    
                    $season = Season::firstOrCreate(
                        ['tv_show_id' => $tvShow->id, 'number' => $seasonData['season_number']],
                        [
                            'media_id' => $movie->id,
                            'title' => $seasonData['name'],
                            'description' => $seasonData['overview'] ?? null,
                        ]
                    );

                    // Get season details from TMDB and import episodes
                    $this->importSeasonEpisodes($tmdbService, $tvShow, $season, $seasonData['season_number']);
                }
            });

            Notification::make()
                ->success()
                ->title('TMDB Data Imported')
                ->body('Successfully imported all metadata, seasons, and episodes from TMDB.')
                ->send();

            // Refresh form data - set thumbnail_url and backdrop_url if they're URLs
            $formData = [
                'tmdb_id' => $tvShow->tmdb_id,
                'title' => $tvShow->title,
                'description' => $tvShow->description,
                'rating' => $tvShow->rating,
                'release_date' => $tvShow->release_date,
                'duration' => $tvShow->duration,
            ];
            
            // If thumbnail/backdrop are URLs (not file paths), set them as URL fields
            if ($tvShow->thumbnail && (str_starts_with($tvShow->thumbnail, 'http://') || str_starts_with($tvShow->thumbnail, 'https://'))) {
                $formData['thumbnail_url'] = $tvShow->thumbnail;
            } else {
                $formData['thumbnail'] = $tvShow->thumbnail;
            }
            
            if ($tvShow->backdrop && (str_starts_with($tvShow->backdrop, 'http://') || str_starts_with($tvShow->backdrop, 'https://'))) {
                $formData['backdrop_url'] = $tvShow->backdrop;
            } else {
                $formData['backdrop'] = $tvShow->backdrop;
            }
            
            $this->form->fill($formData);

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('TMDB Import Error')
                ->body('Error importing TMDB data: ' . $e->getMessage())
                ->send();
        }
    }

    private function importSeasonsAndEpisodes(): void
    {
        try {
            $tvShow = $this->record;
            
            if (!$tvShow->tmdb_id) {
                Notification::make()
                    ->warning()
                    ->title('TMDB ID Required')
                    ->body('Please set the TMDB ID for this TV show first.')
                    ->send();
                return;
            }

            $tmdbService = app(TmdbService::class);
            
            // Get TV show details to get all seasons
            $tmdbData = $tmdbService->getTvShowDetails($tvShow->tmdb_id);
            
            if (!$tmdbData || !isset($tmdbData['seasons'])) {
                Notification::make()
                    ->warning()
                    ->title('Import Failed')
                    ->body('Could not fetch seasons from TMDB.')
                    ->send();
                return;
            }

            // Find or create corresponding movie entry
            $movie = Movie::where('media_type', 'SERIES')
                ->where('title', $tvShow->title)
                ->first();
            
            if (!$movie) {
                $movie = Movie::create([
                    'title' => $tvShow->title,
                    'slug' => $tvShow->slug,
                    'description' => $tvShow->description,
                    'thumbnail' => $tvShow->thumbnail,
                    'backdrop' => $tvShow->backdrop,
                    'rating' => $tvShow->rating,
                    'release_date' => $tvShow->release_date,
                    'category_id' => $tvShow->category_id,
                    'vj_id' => $tvShow->vj_id,
                    'media_type' => 'SERIES',
                    'access_type' => $tvShow->access_type,
                    'is_free' => $tvShow->is_free,
                    'is_premium' => $tvShow->is_premium,
                    'price_rent' => $tvShow->price_rent,
                    'price_buy' => $tvShow->price_buy,
                    'is_active' => $tvShow->is_active,
                ]);
            }

            $seasonsImported = 0;
            $episodesImported = 0;

            DB::transaction(function () use ($tmdbData, $tvShow, $tmdbService, $movie, &$seasonsImported, &$episodesImported) {
                foreach ($tmdbData['seasons'] as $seasonData) {
                    if (($seasonData['season_number'] ?? 0) == 0) continue; // Skip specials
                    
                    $season = Season::firstOrCreate(
                        ['tv_show_id' => $tvShow->id, 'number' => $seasonData['season_number']],
                        [
                            'media_id' => $movie->id,
                            'title' => $seasonData['name'] ?? "Season {$seasonData['season_number']}",
                            'description' => $seasonData['overview'] ?? null,
                        ]
                    );
                    
                    // Update media_id if it was missing
                    if (!$season->media_id) {
                        $season->update(['media_id' => $movie->id]);
                    }

                    $seasonsImported++;
                    
                    // Import episodes for this season
                    $episodeCount = $this->importSeasonEpisodes($tmdbService, $tvShow, $season, $seasonData['season_number']);
                    $episodesImported += $episodeCount;
                }
            });

            Notification::make()
                ->success()
                ->title('Seasons & Episodes Imported')
                ->body("Successfully imported {$seasonsImported} seasons and {$episodesImported} episodes.")
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Import Error')
                ->body('Error importing seasons and episodes: ' . $e->getMessage())
                ->send();
        }
    }

    private function importSeasonEpisodes(TmdbService $tmdbService, TVShow $tvShow, Season $season, int $seasonNumber): int
    {
        $episodeCount = 0;
        
        try {
            // Get season details from TMDB
            $seasonDetails = $tmdbService->getSeasonDetails($tvShow->tmdb_id, $seasonNumber);
            
            if ($seasonDetails && isset($seasonDetails['episodes']) && is_array($seasonDetails['episodes'])) {
                foreach ($seasonDetails['episodes'] as $episodeData) {
                    if (!isset($episodeData['episode_number']) || !isset($episodeData['name'])) {
                        continue;
                    }
                    
                    Episode::updateOrCreate(
                        ['season_id' => $season->id, 'number' => $episodeData['episode_number']],
                        [
                            'title' => $episodeData['name'],
                            'description' => $episodeData['overview'] ?? null,
                            'thumbnail' => $tmdbService->getImageUrl($episodeData['still_path'] ?? null),
                            'duration' => isset($episodeData['runtime']) && $episodeData['runtime'] 
                                ? $tmdbService->formatRuntime($episodeData['runtime']) 
                                : null,
                            'download_enabled' => true,
                        ]
                    );
                    $episodeCount++;
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error importing episodes for season {$seasonNumber}: " . $e->getMessage());
        }
        
        return $episodeCount;
    }
}
