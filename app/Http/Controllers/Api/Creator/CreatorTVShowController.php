<?php

namespace App\Http\Controllers\Api\Creator;

use App\Events\ShowPublished;
use App\Models\TVShow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreatorTVShowController extends CreatorBaseController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isCreator() && !$user->isAdmin()) {
            return $this->notCreator();
        }

        $query = $this->creatorTvShowQuery($user)->with(['genres'])->withCount('seasons');

        if ($request->has('status')) {
            $query->where('publish_status', $request->get('status'));
        }

        if ($request->has('q')) {
            $query->where('title', 'like', '%' . $request->get('q') . '%');
        }

        $shows = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $shows->getCollection()->map(function ($s) {
                $formatted = $this->formatTvShow($s);
                $formatted['seasons_count'] = $s->seasons_count ?? 0;
                return $formatted;
            }),
            'meta' => [
                'current_page' => $shows->currentPage(),
                'last_page' => $shows->lastPage(),
                'total' => $shows->total(),
                'per_page' => $shows->perPage(),
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
            'tmdb_id'          => ['nullable', 'integer'],
            'tagline'          => ['nullable', 'string', 'max:500'],
            'thumbnail'        => ['nullable', 'image', 'max:5120'],
            'backdrop'         => ['nullable', 'image', 'max:10240'],
            'thumbnail_url'    => ['nullable', 'string', 'max:2048'], // URL or storage path
            'backdrop_url'     => ['nullable', 'string', 'max:2048'],
        ]);

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

        // Unique slug
        $slug = Str::slug($validated['title']);
        $baseSlug = $slug;
        $i = 1;
        while (TVShow::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i++;
        }

        $showData = [
            'title'            => $validated['title'],
            'slug'             => $slug,
            'description'      => $validated['description'] ?? '',
            'category_id'      => $validated['category_id'] ?? 2,
            'release_date'     => $validated['release_date'] ?? null,
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
            'is_active'        => false,
            'publish_status'   => 'draft',
        ];

        if ($user->isVJ()) {
            $vj = $this->resolveVjProfile($user);
            $showData['vj_id'] = $vj?->id;
        } elseif ($user->isMediaLibrary()) {
            $library = $this->resolveMediaLibraryProfile($user);
            $showData['media_library_id'] = $library?->id;
        }

        $show = TVShow::create($showData);

        if (!empty($validated['genres'])) {
            $show->genres()->sync($validated['genres']);
        }

        $show->load('genres');

        return response()->json([
            'success' => true,
            'message' => 'TV show draft created successfully.',
            'data'    => $this->formatTvShow($show),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $show = $this->creatorTvShowQuery($user)->with(['genres', 'seasons.episodes'])->find($id);

        if (!$show) {
            return response()->json(['success' => false, 'message' => 'TV show not found.'], 404);
        }

        $data = $this->formatTvShow($show);
        $data['seasons'] = $show->seasons->map(function ($season) {
            return [
                'id' => $season->id,
                'season_number' => $season->number,
                'title' => $season->title,
                'episodes' => $season->episodes->map(fn($ep) => [
                    'id' => $ep->id,
                    'episode_number' => $ep->number,
                    'title' => $ep->title,
                    'duration' => $ep->duration,
                    'is_active' => $ep->is_active,
                ]),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $show = $this->creatorTvShowQuery($user)->find($id);

        if (!$show) {
            return response()->json(['success' => false, 'message' => 'TV show not found or not authorized.'], 404);
        }

        $validated = $request->validate([
            'title'            => ['sometimes', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'release_date'     => ['nullable', 'date'],
            'certificate'      => ['nullable', 'string', 'max:10'],
            'country'          => ['nullable', 'string', 'max:100'],
            'language'         => ['nullable', 'string', 'max:100'],
            'is_free'          => ['sometimes', 'boolean'],
            'is_premium'       => ['sometimes', 'boolean'],
            'price_rent'       => ['nullable', 'numeric', 'min:0'],
            'price_buy'        => ['nullable', 'numeric', 'min:0'],
            'genres'           => ['nullable', 'array'],
            'genres.*'         => ['integer', 'exists:genres,id'],
            'tagline'          => ['nullable', 'string', 'max:500'],
            'thumbnail'        => ['nullable', 'image', 'max:5120'],
            'backdrop'         => ['nullable', 'image', 'max:10240'],
            'thumbnail_url'    => ['nullable', 'string', 'max:2048'],
            'backdrop_url'     => ['nullable', 'string', 'max:2048'],
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

        $show->fill($validated);
        $show->save();

        if ($genres !== null) {
            $show->genres()->sync($genres);
        }

        $show->load('genres');

        return response()->json([
            'success' => true,
            'message' => 'TV show updated.',
            'data'    => $this->formatTvShow($show),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $show = $this->creatorTvShowQuery($user)->find($id);

        if (!$show) {
            return response()->json(['success' => false, 'message' => 'TV show not found or not authorized.'], 404);
        }

        $show->update(['is_active' => false, 'publish_status' => 'draft']);

        return response()->json(['success' => true, 'message' => 'TV show unpublished.']);
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $show = $this->creatorTvShowQuery($user)->find($id);

        if (!$show) {
            return response()->json(['success' => false, 'message' => 'TV show not found or not authorized.'], 404);
        }

        if ($user->isAdmin()) {
            $show->update(['publish_status' => 'published', 'is_active' => true]);
            $message = 'TV show published.';
            event(new ShowPublished($show->fresh()));
        } else {
            $show->update(['publish_status' => 'pending_review']);
            $message = 'TV show submitted for review.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['publish_status' => $show->publish_status],
        ]);
    }

    public function seasons(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $show = $this->creatorTvShowQuery($user)->with(['seasons.episodes'])->find($id);

        if (!$show) {
            return response()->json(['success' => false, 'message' => 'TV show not found.'], 404);
        }

        $seasons = $show->seasons->map(function ($season) {
            return [
                'id' => $season->id,
                'season_number' => $season->number,
                'title' => $season->title,
                'air_date' => $season->air_date ?? null,
                'episode_count' => $season->episodes->count(),
                'episodes' => $season->episodes->map(fn($ep) => [
                    'id' => $ep->id,
                    'episode_number' => $ep->number,
                    'title' => $ep->title,
                    'description' => $ep->description,
                    'duration' => $ep->duration,
                    'thumbnail' => $ep->thumbnail,
                    'is_active' => $ep->is_active,
                ]),
            ];
        });

        return response()->json(['success' => true, 'data' => $seasons]);
    }
}
