<?php

namespace App\Http\Controllers\Api\Creator;

use App\Events\ShowPublished;
use App\Models\TVShow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\CreatorContentWorkflowService;
use App\Services\CreatorContentRules;
use App\Services\CreatorMonetizationService;

class CreatorTVShowController extends CreatorBaseController
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

        if (! $this->creatorCanSubmit($user)) {
            return $this->notCreator();
        }

        $validated = $request->validate($this->rules->tvShow());

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
            'imdb_id'          => $validated['imdb_id'] ?? null,
            'original_title'   => $validated['original_title'] ?? null,
            'homepage'         => $validated['homepage'] ?? null,
            'status'           => $validated['status'] ?? null,
            'networks'         => $validated['networks'] ?? null,
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
            'is_active'        => false,
            'publish_status'   => 'draft',
        ];

        if ($this->resolveVjProfile($user)) {
            $vj = $this->resolveVjProfile($user);
            $showData['vj_id'] = $vj?->id;
        } elseif ($this->resolveMediaLibraryProfile($user)) {
            $library = $this->resolveMediaLibraryProfile($user);
            $showData['media_library_id'] = $library?->id;
        }

        $show = TVShow::create($showData);
        $this->workflow->initialize($show, $user, $user->isAdmin() ? 'administrator' : 'creator');

        if (!empty($validated['genres'])) {
            $show->genres()->sync($validated['genres']);
        }
        if (! empty($validated['actors'])) {
            $show->actors()->sync(collect($validated['actors'])->mapWithKeys(
                fn (array $actor, int $index) => [
                    $actor['actor_id'] => ['role' => $actor['role'] ?? null, 'order' => $actor['order'] ?? $index],
                ]
            )->all());
        }
        if (! empty($validated['monetization'])) {
            $this->monetization->configure($show, $user, $validated['monetization']);
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
        $show = $this->creatorTvShowQuery($user)->with([
            'genres', 'actors', 'subtitles', 'monetizationSetting',
            'creatorReviews' => fn ($query) => $query->latest('reviewed_at'),
            'seasons.episodes.videoSources',
            'seasons.episodes.subtitles',
        ])->find($id);

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

        $validated = $request->validate($this->rules->tvShow(true));

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
        $monetization = $validated['monetization'] ?? null;
        unset($validated['monetization']);
        $actors = $validated['actors'] ?? null;
        unset($validated['actors']);
        if (array_key_exists('ownership_declaration', $validated)) {
            if ($validated['ownership_declaration']) {
                $validated['ownership_declaration_accepted_at'] = now();
            }
            unset($validated['ownership_declaration']);
        }

        $show->fill($validated);
        $show->save();

        if ($genres !== null) {
            $show->genres()->sync($genres);
        }
        if ($monetization !== null) {
            $this->monetization->configure($show, $user, $monetization);
        }
        if ($actors !== null) {
            $show->actors()->sync(collect($actors)->mapWithKeys(
                fn (array $actor, int $index) => [
                    $actor['actor_id'] => ['role' => $actor['role'] ?? null, 'order' => $actor['order'] ?? $index],
                ]
            )->all());
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
            $this->workflow->moderate($show, $user, 'approved');
            $show->update([
                'publication_status' => 'published',
                'publish_status' => 'published',
                'published_by' => $user->id,
                'published_at' => now(),
                'is_active' => true,
            ]);
            $message = 'TV show published.';
            event(new ShowPublished($show->fresh()));
        } else {
            $this->workflow->submitForReview($show, $user, $request->boolean('publish_after_approval'));
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
