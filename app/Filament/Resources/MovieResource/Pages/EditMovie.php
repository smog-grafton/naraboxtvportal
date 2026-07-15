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
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class EditMovie extends EditRecord
{
    protected static string $resource = MovieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_tmdb')
                ->label('Import from TMDB')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Import from TMDB')
                ->modalDescription('This will fetch and import all metadata from TMDB. Existing data may be overwritten.')
                ->form([
                    \Filament\Forms\Components\TextInput::make('tmdb_id')
                        ->label('TMDB ID')
                        ->numeric()
                        ->required()
                        ->helperText('Enter the TMDB ID of the movie'),
                ])
                ->action(function (array $data) {
                    $this->importTmdbData($data['tmdb_id']);
                }),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $movie = $this->record;
        $formState = $this->form->getState();

        if (!empty($formState['send_push_on_save'])) {
            $title = $formState['push_title'] ?: $movie->title;
            $body = $formState['push_body'] ?: 'Updated movie: ' . $movie->title;

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
                name: $formState['email_subject_override'] ?: ('Movie update: ' . $movie->title),
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

    private function importTmdbData(int $tmdbId): void
    {
        try {
            $movie = $this->record;
            $tmdbService = app(TmdbService::class);
            $tmdbData = $tmdbService->getMovieDetails($tmdbId);

            if (!$tmdbData) {
                Notification::make()
                    ->warning()
                    ->title('TMDB Import Failed')
                    ->body('Could not fetch data from TMDB. Please check the TMDB ID.')
                    ->send();
                return;
            }

            $formattedData = $tmdbService->formatMovieData($tmdbData);

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

            DB::transaction(function () use ($movie, $formattedData, $tmdbService, $thumbnailPath, $backdropPath) {
                // Update movie with TMDB data
                $movie->update([
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
                    'budget' => $formattedData['budget'],
                    'revenue' => $formattedData['revenue'],
                    'status' => $formattedData['status'],
                    'homepage' => $formattedData['homepage'],
                    'popularity' => $formattedData['popularity'],
                    'vote_count' => $formattedData['vote_count'],
                    'original_language' => $formattedData['original_language'],
                    'certificate' => $formattedData['certificate'],
                    'country' => $formattedData['country'],
                    'language' => $formattedData['language'],
                    'production_companies' => $formattedData['production_companies'],
                    'production_countries' => $formattedData['production_countries'],
                ]);

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

            // Refresh form data - set thumbnail_url and backdrop_url if they're URLs
            $formData = [
                'tmdb_id' => $movie->tmdb_id,
                'title' => $movie->title,
                'description' => $movie->description,
                'rating' => $movie->rating,
                'release_date' => $movie->release_date,
                'duration' => $movie->duration,
            ];
            
            // If thumbnail/backdrop are URLs (not file paths), set them as URL fields
            if ($movie->thumbnail && (str_starts_with($movie->thumbnail, 'http://') || str_starts_with($movie->thumbnail, 'https://'))) {
                $formData['thumbnail_url'] = $movie->thumbnail;
            } else {
                $formData['thumbnail'] = $movie->thumbnail;
            }
            
            if ($movie->backdrop && (str_starts_with($movie->backdrop, 'http://') || str_starts_with($movie->backdrop, 'https://'))) {
                $formData['backdrop_url'] = $movie->backdrop;
            } else {
                $formData['backdrop'] = $movie->backdrop;
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
}
