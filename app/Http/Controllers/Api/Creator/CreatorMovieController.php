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

class CreatorMovieController extends CreatorBaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isCreator() && !$user->isAdmin()) {
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

        if (!$user->isCreator() && !$user->isAdmin()) {
            return $this->notCreator();
        }

        $validated = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'release_date'     => ['nullable', 'date'],
            'duration'         => ['nullable', 'string', 'max:20'],
            'certificate'      => ['nullable', 'string', 'max:10'],
            'country'          => ['nullable', 'string', 'max:100'],
            'language'         => ['nullable', 'string', 'max:100'],
            'original_language'=> ['nullable', 'string', 'max:100'],
            'is_free'          => ['sometimes', 'boolean'],
            'is_premium'       => ['sometimes', 'boolean'],
            'price_rent'       => ['nullable', 'numeric', 'min:0'],
            'price_buy'        => ['nullable', 'numeric', 'min:0'],
            'genres'           => ['nullable', 'array'],
            'genres.*'         => ['integer', 'exists:genres,id'],
            'category_id'      => ['nullable', 'integer', 'exists:categories,id'],
            'vj_id'            => ['nullable', 'integer', 'exists:vjs,id'],
            'tmdb_id'          => ['nullable', 'integer'],
            'tagline'          => ['nullable', 'string', 'max:500'],
            'thumbnail'        => ['nullable', 'image', 'max:5120'],
            'backdrop'         => ['nullable', 'image', 'max:10240'],
            'thumbnail_url'    => ['nullable', 'string', 'max:2048'], // URL or storage path (e.g. tmdb/posters/xxx.jpg)
            'backdrop_url'     => ['nullable', 'string', 'max:2048'],
            'rating'           => ['nullable', 'numeric', 'min:0', 'max:10'],
            'actors'           => ['nullable', 'array'],
            'actors.*.actor_id'=> ['required_with:actors', 'integer', 'exists:actors,id'],
            'actors.*.role'    => ['nullable', 'string', 'max:255'],
            'actors.*.order'   => ['nullable', 'integer', 'min:0'],
        ]);

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
        if ($user->isVJ()) {
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
            'rating'           => $validated['rating'] ?? null,
            'is_active'        => false, // starts inactive until published
            'publish_status'   => 'draft',
        ];

        // Assign ownership
        if ($user->isVJ()) {
            $vj = $this->resolveVjProfile($user);
            $movieData['vj_id'] = $vj?->id;
        } elseif ($user->isMediaLibrary()) {
            $library = $this->resolveMediaLibraryProfile($user);
            $movieData['media_library_id'] = $library?->id;
            // Media libraries can assign a VJ when creating VJ Translated content
            $movieData['vj_id'] = $validated['vj_id'] ?? null;
        } elseif ($user->isAdmin()) {
            $movieData['vj_id'] = $validated['vj_id'] ?? null;
        }

        $movie = Movie::create($movieData);

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
        $movie = $this->creatorMovieQuery($user)->with(['genres', 'videoSources'])->find($id);

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

        $validated = $request->validate([
            'title'            => ['sometimes', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'release_date'     => ['nullable', 'date'],
            'duration'         => ['nullable', 'string', 'max:20'],
            'certificate'      => ['nullable', 'string', 'max:10'],
            'country'          => ['nullable', 'string', 'max:100'],
            'language'         => ['nullable', 'string', 'max:100'],
            'original_language'=> ['nullable', 'string', 'max:100'],
            'is_free'          => ['sometimes', 'boolean'],
            'is_premium'       => ['sometimes', 'boolean'],
            'price_rent'       => ['nullable', 'numeric', 'min:0'],
            'price_buy'        => ['nullable', 'numeric', 'min:0'],
            'genres'           => ['nullable', 'array'],
            'genres.*'         => ['integer', 'exists:genres,id'],
            'category_id'      => ['nullable', 'integer', 'exists:categories,id'],
            'vj_id'            => ['nullable', 'integer', 'exists:vjs,id'],
            'tagline'          => ['nullable', 'string', 'max:500'],
            'thumbnail'        => ['nullable', 'image', 'max:5120'],
            'backdrop'         => ['nullable', 'image', 'max:10240'],
            'thumbnail_url'    => ['nullable', 'string', 'max:2048'],
            'backdrop_url'     => ['nullable', 'string', 'max:2048'],
            'rating'           => ['nullable', 'numeric', 'min:0', 'max:10'],
            'actors'           => ['nullable', 'array'],
            'actors.*.actor_id'=> ['required_with:actors', 'integer', 'exists:actors,id'],
            'actors.*.role'    => ['nullable', 'string', 'max:255'],
            'actors.*.order'   => ['nullable', 'integer', 'min:0'],
        ]);

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

        if ($user->isMediaLibrary() && array_key_exists('vj_id', $validated)) {
            $movie->vj_id = $validated['vj_id'];
            unset($validated['vj_id']);
        } elseif ($user->isAdmin() && array_key_exists('vj_id', $validated)) {
            $movie->vj_id = $validated['vj_id'];
            unset($validated['vj_id']);
        }

        $movie->fill($validated);
        $movie->save();

        if ($genres !== null) {
            $movie->genres()->sync($genres);
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

        // For admins — auto-publish; for creators — set pending review
        if ($user->isAdmin()) {
            $movie->update(['publish_status' => 'published', 'is_active' => true]);
            $message = 'Movie published.';
            event(new MoviePublished($movie->fresh()));
        } else {
            $movie->update(['publish_status' => 'pending_review']);
            $message = 'Movie submitted for review. Admin will review and publish it.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => ['publish_status' => $movie->publish_status],
        ]);
    }
}
