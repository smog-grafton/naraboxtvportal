<?php

namespace App\Http\Controllers\Api\Creator;

use App\Events\MoviePublished;
use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\CreatorContentWorkflowService;
use App\Services\CreatorContentRules;
use App\Services\CreatorMonetizationService;

class CreatorMovieController extends CreatorBaseController
{
    public function __construct(
        private readonly CreatorContentWorkflowService $workflow,
        private readonly CreatorMonetizationService $monetization,
        private readonly CreatorContentRules $rules
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->creatorAccessAllowed($user)) {
            return $this->notCreator();
        }

        $query = $this->creatorMovieQuery($user)
            ->with(['genres', 'vj'])
            ->withCount('videoSources');

        if ($request->has('status')) {
            $query->where('publish_status', $request->get('status'));
        }

        if ($request->has('q')) {
            $query->where('title', 'like', '%' . $request->get('q') . '%');
        }

        $movies = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $movies->getCollection()->map(fn($m) => array_merge(
                $this->formatMovie($m),
                ['sources_count' => $m->video_sources_count]
            )),
            'meta' => [
                'current_page' => $movies->currentPage(),
                'last_page' => $movies->lastPage(),
                'total' => $movies->total(),
                'per_page' => $movies->perPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->creatorCanSubmit($user)) {
            return $this->notCreator();
        }

        $validated = $request->validate($this->rules->movie());

        // Handle image uploads
        $thumbnailPath = null;
        $backdropPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
        } elseif (!empty($validated['thumbnail_url'])) {
            $thumbnailPath = $validated['thumbnail_url'];
        }
        if ($request->hasFile('backdrop')) {
            $backdropPath = $request->file('backdrop')->store('backdrops', 'public');
        } elseif (!empty($validated['backdrop_url'])) {
            $backdropPath = $validated['backdrop_url'];
        }

        // Generate unique slug
        $slug = Str::slug($validated['title']);
        if ($this->resolveVjProfile($user)) {
            $vj = $this->resolveVjProfile($user);
            $slug = $slug . '-' . ($vj ? Str::slug($vj->name) : $user->id);
        }
        // Ensure uniqueness
        $baseSlug = $slug;
        $i = 1;
        while (Movie::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i++;
        }

        $movieData = [
            'title'            => $validated['title'],
            'slug'             => $slug,
            'description'      => $validated['description'] ?? '',
            'media_type'       => 'MOVIE',
            'category_id'      => $validated['category_id'] ?? 1,
            'release_date'     => $validated['release_date'] ?? null,
            'duration'         => $validated['duration'] ?? null,
            'certificate'      => $validated['certificate'] ?? null,
            'country'          => $validated['country'] ?? null,
            'language'         => $validated['language'] ?? null,
            'original_language'=> $validated['original_language'] ?? null,
            'is_free'          => $validated['is_free'] ?? false,
            'is_premium'       => $validated['is_premium'] ?? false,
            'price_rent'       => $validated['price_rent'] ?? null,
            'price_buy'        => $validated['price_buy'] ?? null,
            'thumbnail'        => $thumbnailPath,
            'backdrop'         => $backdropPath,
            'tmdb_id'          => $validated['tmdb_id'] ?? null,
            'tagline'          => $validated['tagline'] ?? null,
            'rating'           => $validated['rating'] ?? 0,
            'imdb_id'          => $validated['imdb_id'] ?? null,
            'original_title'   => $validated['original_title'] ?? null,
            'homepage'         => $validated['homepage'] ?? null,
            'production_companies' => $validated['production_companies'] ?? null,
            'production_countries' => $validated['production_countries'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'director' => $validated['director'] ?? null,
            'translation_language' => $validated['translation_language'] ?? null,
            'trailer_url' => $validated['trailer_url'] ?? null,
            'tags' => $validated['tags'] ?? null,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
            'download_enabled' => $validated['download_enabled'] ?? true,
            'scheduled_for' => $validated['scheduled_for'] ?? null,
            'ownership_declaration_accepted_at' => ! empty($validated['ownership_declaration']) ? now() : null,
            'is_active'        => false, // starts inactive until published
            'publish_status'   => 'draft',
        ];

        // Assign ownership
        if ($this->resolveVjProfile($user)) {
            $vj = $this->resolveVjProfile($user);
            $movieData['vj_id'] = $vj?->id;
        } elseif ($this->resolveMediaLibraryProfile($user)) {
            $library = $this->resolveMediaLibraryProfile($user);
            $movieData['media_library_id'] = $library?->id;
        } elseif ($user->isAdmin()) {
            $movieData['vj_id'] = $validated['vj_id'] ?? null;
        }

        $movie = Movie::create($movieData);
        $this->workflow->initialize($movie, $user, $user->isAdmin() ? 'administrator' : 'creator');

        // Sync genres
        if (!empty($validated['genres'])) {
            $movie->genres()->sync($validated['genres']);
        }

        // Sync actors
        if (!empty($validated['actors'])) {
            $sync = collect($validated['actors'])->mapWithKeys(function ($a, $idx) {
                return [$a['actor_id'] => ['role' => $a['role'] ?? null, 'order' => $a['order'] ?? $idx]];
            })->toArray();
            $movie->actors()->sync($sync);
        }

        if (! empty($validated['monetization'])) {
            $this->monetization->configure($movie, $user, $validated['monetization']);
        }

        $movie->load(['genres', 'actors']);

        return response()->json([
            'success' => true,
            'message' => 'Movie draft created successfully.',
            'data'    => $this->formatMovie($movie),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $movie = $this->creatorMovieQuery($user)->with([
            'genres', 'actors', 'videoSources', 'subtitles', 'monetizationSetting',
            'creatorReviews' => fn ($query) => $query->latest('reviewed_at'),
        ])->find($id);

        if (!$movie) {
            return response()->json(['success' => false, 'message' => 'Movie not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatMovie($movie, true),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $movie = $this->creatorMovieQuery($user)->find($id);

        if (!$movie) {
            return response()->json(['success' => false, 'message' => 'Movie not found or not authorized.'], 404);
        }

        $validated = $request->validate($this->rules->movie(true));

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('thumbnails', 'public');
        } elseif (array_key_exists('thumbnail_url', $validated) && $validated['thumbnail_url'] !== null && $validated['thumbnail_url'] !== '') {
            $validated['thumbnail'] = $validated['thumbnail_url'];
        }
        unset($validated['thumbnail_url']);

        if ($request->hasFile('backdrop')) {
            $validated['backdrop'] = $request->file('backdrop')->store('backdrops', 'public');
        } elseif (array_key_exists('backdrop_url', $validated) && $validated['backdrop_url'] !== null && $validated['backdrop_url'] !== '') {
            $validated['backdrop'] = $validated['backdrop_url'];
        }
        unset($validated['backdrop_url']);

        $genres = $validated['genres'] ?? null;
        unset($validated['genres']);
        $actors = $validated['actors'] ?? null;
        unset($validated['actors']);
        $monetization = $validated['monetization'] ?? null;
        unset($validated['monetization']);
        if (array_key_exists('ownership_declaration', $validated)) {
            if ($validated['ownership_declaration']) {
                $validated['ownership_declaration_accepted_at'] = now();
            }
            unset($validated['ownership_declaration']);
        }

        if ($user->isAdmin() && array_key_exists('vj_id', $validated)) {
            $movie->vj_id = $validated['vj_id'];
            unset($validated['vj_id']);
        } else {
            unset($validated['vj_id']);
        }

        $movie->fill($validated);
        $movie->save();

        if ($genres !== null) {
            $movie->genres()->sync($genres);
        }
        if ($actors !== null) {
            $sync = collect($actors)->mapWithKeys(function ($actor, $index) {
                return [$actor['actor_id'] => [
                    'role' => $actor['role'] ?? null,
                    'order' => $actor['order'] ?? $index,
                ]];
            })->toArray();
            $movie->actors()->sync($sync);
        }
        if ($monetization !== null) {
            $this->monetization->configure($movie, $user, $monetization);
        }

        $movie->load('genres');

        return response()->json([
            'success' => true,
            'message' => 'Movie updated.',
            'data'    => $this->formatMovie($movie),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $movie = $this->creatorMovieQuery($user)->find($id);

        if (!$movie) {
            return response()->json(['success' => false, 'message' => 'Movie not found or not authorized.'], 404);
        }

        $movie->update(['is_active' => false, 'publish_status' => 'draft']);

        return response()->json(['success' => true, 'message' => 'Movie unpublished.']);
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $movie = $this->creatorMovieQuery($user)->find($id);

        if (!$movie) {
            return response()->json(['success' => false, 'message' => 'Movie not found or not authorized.'], 404);
        }

        if ($user->isAdmin()) {
            $this->workflow->moderate($movie, $user, 'approved');
            $movie->update([
                'publication_status' => 'published',
                'publish_status' => 'published',
                'published_by' => $user->id,
                'published_at' => now(),
                'is_active' => true,
            ]);
            $message = 'Movie published.';
            event(new MoviePublished($movie->fresh()));
        } else {
            $this->workflow->submitForReview($movie, $user, $request->boolean('publish_after_approval'));
            $message = 'Movie submitted for review. Admin will review and publish it.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => ['publish_status' => $movie->publish_status],
        ]);
    }
}
