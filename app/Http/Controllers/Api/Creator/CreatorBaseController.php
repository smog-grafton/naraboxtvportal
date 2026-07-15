<?php

namespace App\Http\Controllers\Api\Creator;

use App\Http\Controllers\Controller;
use App\Models\MediaLibrary;
use App\Models\User;
use App\Models\VJ;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

abstract class CreatorBaseController extends Controller
{
    /**
     * Resolve thumbnail or backdrop to a full URL when it's a storage path.
     */
    protected function resolveMediaUrl(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        if (Storage::disk('public')->exists($value)) {
            return url(Storage::disk('public')->url($value));
        }
        return $value;
    }
    /**
     * Resolve the VJ profile for a VJ creator user.
     */
    protected function resolveVjProfile(User $user): ?VJ
    {
        return $user->isVJ() ? $user->vjProfile : null;
    }

    /**
     * Resolve the Media Library profile for a media_library creator user.
     */
    protected function resolveMediaLibraryProfile(User $user): ?MediaLibrary
    {
        return $user->isMediaLibrary() ? $user->mediaLibraryProfile : null;
    }

    /**
     * Scope a movie query to only include movies owned by the authenticated creator.
     */
    protected function creatorMovieQuery(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = \App\Models\Movie::query()->where('media_type', 'MOVIE');

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isVJ()) {
            $vj = $this->resolveVjProfile($user);
            if (!$vj) {
                return $query->whereRaw('1 = 0');
            }
            return $query->where('vj_id', $vj->id);
        }

        if ($user->isMediaLibrary()) {
            $library = $this->resolveMediaLibraryProfile($user);
            if (!$library) {
                return $query->whereRaw('1 = 0');
            }
            return $query->where('media_library_id', $library->id);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Scope a TV show query to only include shows owned by the authenticated creator.
     */
    protected function creatorTvShowQuery(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = \App\Models\TVShow::query();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isVJ()) {
            $vj = $this->resolveVjProfile($user);
            if (!$vj) {
                return $query->whereRaw('1 = 0');
            }
            return $query->where('vj_id', $vj->id);
        }

        if ($user->isMediaLibrary()) {
            $library = $this->resolveMediaLibraryProfile($user);
            if (!$library) {
                return $query->whereRaw('1 = 0');
            }
            return $query->where('media_library_id', $library->id);
        }

        return $query->whereRaw('1 = 0');
    }

    protected function notCreator(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Your account is not a verified creator account. Please complete your creator application first.',
        ], 403);
    }

    protected function formatMovie(\App\Models\Movie $movie, bool $withSources = false): array
    {
        $data = [
            'id' => $movie->id,
            'title' => $movie->title,
            'slug' => $movie->slug,
            'description' => $movie->description,
            'thumbnail' => $this->resolveMediaUrl($movie->thumbnail),
            'backdrop' => $this->resolveMediaUrl($movie->backdrop),
            'rating' => $movie->rating,
            'release_date' => $movie->release_date?->format('Y-m-d'),
            'duration' => $movie->duration,
            'certificate' => $movie->certificate,
            'country' => $movie->country,
            'language' => $movie->language,
            'original_language' => $movie->original_language,
            'is_free' => (bool) $movie->is_free,
            'is_premium' => (bool) $movie->is_premium,
            'price_rent' => $movie->price_rent,
            'price_buy' => $movie->price_buy,
            'is_active' => (bool) $movie->is_active,
            'publish_status' => $movie->publish_status ?? 'draft',
            'cdn_asset_id' => $movie->cdn_asset_id,
            'vj_id' => $movie->vj_id,
            'media_library_id' => $movie->media_library_id,
            'tmdb_id' => $movie->tmdb_id,
            'tagline' => $movie->tagline,
            'genres' => $movie->relationLoaded('genres')
                ? $movie->genres->map(fn($g) => ['id' => $g->id, 'name' => $g->name])
                : [],
            'views_count' => (int) ($movie->views_count ?? 0),
            'sources_count' => $withSources ? $movie->videoSources()->count() : null,
            'created_at' => $movie->created_at?->toIso8601String(),
            'updated_at' => $movie->updated_at?->toIso8601String(),
        ];

        if ($withSources) {
            $data['sources'] = $movie->videoSources->map(fn($s) => $this->formatVideoSource($s));
        }

        return $data;
    }

    protected function formatTvShow(\App\Models\TVShow $show): array
    {
        return [
            'id' => $show->id,
            'title' => $show->title,
            'slug' => $show->slug,
            'description' => $show->description,
            'thumbnail' => $this->resolveMediaUrl($show->thumbnail),
            'backdrop' => $this->resolveMediaUrl($show->backdrop),
            'rating' => $show->rating,
            'release_date' => $show->release_date?->format('Y-m-d'),
            'certificate' => $show->certificate,
            'country' => $show->country,
            'language' => $show->language,
            'is_free' => (bool) $show->is_free,
            'is_premium' => (bool) $show->is_premium,
            'price_rent' => $show->price_rent,
            'price_buy' => $show->price_buy,
            'is_active' => (bool) $show->is_active,
            'publish_status' => $show->publish_status ?? 'draft',
            'cdn_asset_id' => $show->cdn_asset_id,
            'vj_id' => $show->vj_id,
            'media_library_id' => $show->media_library_id,
            'tmdb_id' => $show->tmdb_id,
            'number_of_seasons' => $show->number_of_seasons,
            'number_of_episodes' => $show->number_of_episodes,
            'genres' => $show->relationLoaded('genres')
                ? $show->genres->map(fn($g) => ['id' => $g->id, 'name' => $g->name])
                : [],
            'views_count' => (int) ($show->views_count ?? 0),
            'created_at' => $show->created_at?->toIso8601String(),
            'updated_at' => $show->updated_at?->toIso8601String(),
        ];
    }

    protected function formatVideoSource(\App\Models\VideoSource $source): array
    {
        $metadata = $source->metadata ?? [];
        return [
            'id' => $source->id,
            'type' => $source->type,
            'url' => $source->url,
            'quality' => $source->quality,
            'format' => $source->format,
            'file_size' => $source->file_size,
            'is_primary' => (bool) $source->is_primary,
            'is_active' => (bool) $source->is_active,
            'cdn_asset_id' => $metadata['cdn_asset_id'] ?? null,
            'cdn_source_id' => $metadata['cdn_source_id'] ?? null,
            'cdn_status' => $metadata['cdn_status'] ?? null,
            'fetch_status' => $metadata['fetch_status'] ?? null,
            'telegram_status' => $metadata['telegram_status'] ?? null,
            'telebot_job_id' => $metadata['telebot_job_id'] ?? null,
            'telebot_status' => $metadata['telebot_status'] ?? null,
            'telebot_progress' => $metadata['telebot_progress'] ?? null,
            'object_key' => $metadata['object_key'] ?? null,
            'public_url' => $metadata['public_url'] ?? null,
            'last_message' => $metadata['last_message'] ?? null,
            'source_role' => $metadata['source_role'] ?? null,
            'created_at' => $source->created_at?->toIso8601String(),
        ];
    }
}
