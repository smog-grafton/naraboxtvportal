<?php

namespace App\Filament\Resources\MovieResource\Pages;

use App\Events\MoviePublished;
use App\Filament\Resources\MovieResource;
use App\Services\CampaignService;
use App\Models\PushNotification;
use App\Services\PushNotificationService;
use App\Services\TmdbService;
use App\Models\Movie;
use App\Models\Genre;
use App\Models\Actor;
use App\Models\Crew;
use App\Models\Trailer;
use App\Models\Keyword;
use App\Models\Collection;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class CreateMovie extends CreateRecord
{
    protected static string $resource = MovieResource::class;

    public function mount(): void
    {
        parent::mount();
        $title = request()->query('title');
        $vj = request()->query('vj');
        if ($title !== null || $vj !== null) {
            $data = $this->form->getState();
            if ($title !== null) {
                $data['title'] = $title;
            }
            if ($vj !== null) {
                $vjModel = \App\Models\VJ::firstOrCreate(['name' => $vj], ['slug' => \Illuminate\Support\Str::slug($vj)]);
                $data['vj_id'] = $vjModel->id;
            }
            $this->form->fill($data);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove hidden fields that shouldn't be saved
        unset($data['tmdb_search'], $data['tmdb_search_results'], $data['tmdb_selected_result'], $data['tmdb_formatted_data']);
        
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
        $movie = $this->record;
        
        // Import TMDB related data (actors, crew, trailers, keywords) if tmdb_id is provided
        if ($movie->tmdb_id) {
            $this->importTmdbRelatedData($movie);
        }

        // Optional push notification
        $formState = $this->form->getState();
        if (!empty($formState['send_push_on_save'])) {
            $title = $formState['push_title'] ?: $movie->title;
            $body = $formState['push_body'] ?: 'New movie added: ' . $movie->title;

            $notification = PushNotification::create([
                'title' => $title,
                'body' => $body,
                'image_url' => $formState['push_image_url'] ?? null,
                'deep_link' => 'app://movie/' . $movie->id,
                'target_platform' => $formState['push_target_platform'] ?? 'all',
                'target_audience' => 'all',
                'provider' => 'default',
                'notification_type' => 'marketing',
                'status' => 'queued',
            ]);

            PushNotificationService::send($notification);
        }

        if ($movie->is_active && ($movie->content_status ?? 'published') === 'published') {
            event(new MoviePublished($movie));
        }

        if (!empty($formState['send_email_on_save']) && $movie->is_active && ($movie->content_status ?? 'published') === 'published') {
            app(CampaignService::class)->queueTemplateCampaign(
                name: $formState['email_subject_override'] ?: ('New movie: ' . $movie->title),
                templateName: 'new_movie_added',
                templateData: [
                    'movie_title' => $movie->title,
                    'watch_url' => rtrim((string) config('app.url'), '/') . '/movies/' . $movie->id,
                    'created_at' => now(),
                ],
                audienceType: $formState['email_audience_type'] ?? 'selected_users',
                sendToAll: ($formState['email_audience_type'] ?? 'selected_users') === 'selected_users',
                marketingOnly: (bool) ($formState['email_marketing_only'] ?? true),
            );
        }
    }

    private function importTmdbRelatedData($movie): void
    {
        try {
            $tmdbService = app(TmdbService::class);
            $tmdbData = $tmdbService->getMovieDetails($movie->tmdb_id);

            if (!$tmdbData) {
                Notification::make()
                    ->warning()
                    ->title('TMDB Import Failed')
                    ->body('Could not fetch data from TMDB. Please check the TMDB ID.')
                    ->send();
                return;
            }

            $formattedData = $tmdbService->formatMovieData($tmdbData);

            DB::transaction(function () use ($movie, $formattedData, $tmdbService) {

                // Sync genres
                $genreIds = [];
                foreach ($formattedData['genres'] as $genreName) {
                    $genre = Genre::firstOrCreate(['name' => $genreName]);
                    $genreIds[] = $genre->id;
                }
                $movie->genres()->sync($genreIds);

                // Sync actors - download and save images
                $movie->actors()->detach();
                foreach ($formattedData['cast'] as $index => $actorData) {
                    $actorImagePath = null;
                    if (!empty($actorData['profile_path'])) {
                        // profile_path is already the raw TMDB path (e.g., "/abc123.jpg")
                        $tmdbPath = ltrim($actorData['profile_path'], '/');
                        $actorImagePath = $tmdbService->downloadImage($tmdbPath, 'w500', 'actor');
                    }
                    
                    $actor = Actor::firstOrCreate(
                        ['name' => $actorData['name']],
                        ['image' => $actorImagePath ?? $actorData['profile_path']]
                    );
                    
                    // Update actor image if downloaded and different
                    if ($actorImagePath && $actor->image !== $actorImagePath) {
                        $actor->update(['image' => $actorImagePath]);
                    }
                    
                    // Attach with pivot data (role and order)
                    $movie->actors()->attach($actor->id, [
                        'role' => $actorData['character'],
                        'order' => $actorData['order'],
                    ]);
                }

                // Create crew members (directors, writers, producers)
                $movie->crew()->delete();
                
                // Directors
                foreach ($formattedData['crew']['directors'] as $director) {
                    Crew::create([
                        'crewable_type' => Movie::class,
                        'crewable_id' => $movie->id,
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
                        'crewable_type' => Movie::class,
                        'crewable_id' => $movie->id,
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
                        'crewable_type' => Movie::class,
                        'crewable_id' => $movie->id,
                        'tmdb_id' => $producer['tmdb_id'],
                        'name' => $producer['name'],
                        'job' => 'Producer',
                        'department' => 'Production',
                        'profile_image' => $producer['profile_path'],
                        'order' => 0,
                    ]);
                }

                // Create trailers
                $movie->trailers()->delete();
                foreach ($formattedData['trailers'] as $trailer) {
                    Trailer::create([
                        'trailerable_type' => Movie::class,
                        'trailerable_id' => $movie->id,
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
                $movie->keywords()->delete();
                foreach ($formattedData['keywords'] as $keywordName) {
                    Keyword::create([
                        'keywordable_type' => Movie::class,
                        'keywordable_id' => $movie->id,
                        'name' => $keywordName,
                    ]);
                }

                // Handle collection
                if ($formattedData['collection']) {
                    $collection = Collection::firstOrCreate(
                        ['tmdb_id' => $formattedData['collection']['id']],
                        [
                            'name' => $formattedData['collection']['name'],
                            'poster_path' => $tmdbService->getImageUrl($formattedData['collection']['poster_path']),
                            'backdrop_path' => $tmdbService->getImageUrl($formattedData['collection']['backdrop_path']),
                        ]
                    );
                    $movie->update(['collection_id' => $collection->id]);
                }
            });

            Notification::make()
                ->success()
                ->title('TMDB Data Imported')
                ->body('Successfully imported all metadata from TMDB.')
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('TMDB Import Error')
                ->body('Error importing TMDB data: ' . $e->getMessage())
                ->send();
        }
    }
}
