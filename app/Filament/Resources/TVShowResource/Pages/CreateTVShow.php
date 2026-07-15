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
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class CreateTVShow extends CreateRecord
{
    protected static string $resource = TVShowResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove hidden fields that shouldn't be saved
        unset($data['tmdb_search'], $data['tmdb_search_results'], $data['tmdb_selected_result'], $data['tmdb_formatted_data'], $data['tmdb_seasons_data']);

        // Ensure primary category_id is in categories pivot
        if (!empty($data['category_id'])) {
            $data['categories'] = $data['categories'] ?? [];
            if (!in_array($data['category_id'], $data['categories'])) {
                $data['categories'][] = $data['category_id'];
            }
        }
        
        // Handle thumbnail: FileUpload returns array, extract first element or use URL
        if (isset($data['thumbnail']) && is_array($data['thumbnail']) && !empty($data['thumbnail'])) {
            $data['thumbnail'] = $data['thumbnail'][0]; // Get first file path
        } elseif (isset($data['thumbnail_url']) && !empty($data['thumbnail_url'])) {
            $data['thumbnail'] = $data['thumbnail_url']; // Use URL as fallback
            unset($data['thumbnail_url']);
        } elseif (empty($data['thumbnail'])) {
            $data['thumbnail'] = null;
        }

        // Handle backdrop: FileUpload returns array, extract first element or use URL
        if (isset($data['backdrop']) && is_array($data['backdrop']) && !empty($data['backdrop'])) {
            $data['backdrop'] = $data['backdrop'][0]; // Get first file path
        } elseif (isset($data['backdrop_url']) && !empty($data['backdrop_url'])) {
            $data['backdrop'] = $data['backdrop_url']; // Use URL as fallback
            unset($data['backdrop_url']);
        } elseif (empty($data['backdrop'])) {
            $data['backdrop'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $tvShow = $this->record;
        
        // Import TMDB related data (actors, crew, trailers, keywords, seasons, episodes) if tmdb_id is provided
        if ($tvShow->tmdb_id) {
            $this->importTmdbRelatedData($tvShow);
        }

        // Optional push notification
        $formState = $this->form->getState();
        if (!empty($formState['send_push_on_save'])) {
            $title = $formState['push_title'] ?: $tvShow->title;
            $body = $formState['push_body'] ?: 'New TV show added: ' . $tvShow->title;

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
                name: $formState['email_subject_override'] ?: ('New TV show: ' . $tvShow->title),
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

    private function importTmdbRelatedData($tvShow): void
    {
        try {
            $tmdbService = app(TmdbService::class);
            $tmdbData = $tmdbService->getTvShowDetails($tvShow->tmdb_id);

            if (!$tmdbData) {
                Notification::make()
                    ->warning()
                    ->title('TMDB Import Failed')
                    ->body('Could not fetch data from TMDB. Please check the TMDB ID.')
                    ->send();
                return;
            }

            $formattedData = $tmdbService->formatTvShowData($tmdbData);

            DB::transaction(function () use ($tvShow, $formattedData, $tmdbService, $tmdbData) {
                // Update TV show with additional TMDB data
                $tvShow->update([
                    'imdb_id' => $formattedData['imdb_id'],
                    'original_title' => $formattedData['original_title'],
                    'tagline' => $formattedData['tagline'],
                    'status' => $formattedData['status'],
                    'homepage' => $formattedData['homepage'],
                    'popularity' => $formattedData['popularity'],
                    'vote_count' => $formattedData['vote_count'],
                    'number_of_seasons' => $formattedData['number_of_seasons'],
                    'number_of_episodes' => $formattedData['number_of_episodes'],
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
                $movie = \App\Models\Movie::where('media_type', 'SERIES')
                    ->where('title', $tvShow->title)
                    ->first();
                
                // If no movie entry exists, create one
                if (!$movie) {
                    $movie = \App\Models\Movie::create([
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

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('TMDB Import Error')
                ->body('Error importing TMDB data: ' . $e->getMessage())
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
