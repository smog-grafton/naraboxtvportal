<?php

namespace App\Http\Controllers\Api\Creator;

use App\Http\Controllers\Controller;
use App\Models\MediaLibrary;
use App\Models\User;
use App\Models\VJ;
use App\Services\CreatorAccessService;
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
        return $user->vjProfile;
    }

    /**
     * Resolve the Media Library profile for a media_library creator user.
     */
    protected function resolveMediaLibraryProfile(User $user): ?MediaLibrary
    {
        return $user->mediaLibraryProfile;
    }

    protected function creatorAccessAllowed(User $user): bool
    {
        return app(CreatorAccessService::class)->workspace($user);
    }

    protected function creatorCanSubmit(User $user): bool
    {
        return app(CreatorAccessService::class)->canSubmitDrafts($user);
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

        $vjId = $this->resolveVjProfile($user)?->id;
        $libraryId = $this->resolveMediaLibraryProfile($user)?->id;

        return $query->where(function ($owner) use ($user, $vjId, $libraryId) {
            $owner->where('submitted_by', $user->id);
            if ($vjId) {
                $owner->orWhere('vj_id', $vjId);
            }
            if ($libraryId) {
                $owner->orWhere('media_library_id', $libraryId);
            }
        });
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

        $vjId = $this->resolveVjProfile($user)?->id;
        $libraryId = $this->resolveMediaLibraryProfile($user)?->id;

        return $query->where(function ($owner) use ($user, $vjId, $libraryId) {
            $owner->where('submitted_by', $user->id);
            if ($vjId) {
                $owner->orWhere('vj_id', $vjId);
            }
            if ($libraryId) {
                $owner->orWhere('media_library_id', $libraryId);
            }
        });
    }

    protected function notCreator(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Start or resume your creator application to access this workspace.',
        ], 403);
    }

    protected function formatMovie(\App\Models\Movie $movie, bool $withSources = false): array
    {
        $data = [
            'id' => $movie->id,
            'title' => $movie->title,
            'slug' => $movie->slug,
            'description' => $movie->description,
            'short_description' => $movie->short_description,
            'original_title' => $movie->original_title,
            'tagline' => $movie->tagline,
            'director' => $movie->director,
            'trailer_url' => $movie->trailer_url,
            'thumbnail' => $this->resolveMediaUrl($movie->thumbnail),
            'backdrop' => $this->resolveMediaUrl($movie->backdrop),
            'rating' => $movie->rating,
            'release_date' => $movie->release_date?->format('Y-m-d'),
            'duration' => $movie->duration,
            'certificate' => $movie->certificate,
            'country' => $movie->country,
            'language' => $movie->language,
            'original_language' => $movie->original_language,
            'translation_language' => $movie->translation_language,
            'category_id' => $movie->category_id,
            'is_free' => (bool) $movie->is_free,
            'is_premium' => (bool) $movie->is_premium,
            'price_rent' => $movie->price_rent,
            'price_buy' => $movie->price_buy,
            'download_enabled' => (bool) $movie->download_enabled,
            'is_active' => (bool) $movie->is_active,
            'publish_status' => $movie->publish_status ?? 'draft',
            'processing_status' => $movie->processing_status ?? 'not_started',
            'editorial_status' => $movie->editorial_status ?? 'not_submitted',
            'publication_status' => $movie->publication_status ?? 'draft',
            'monetization_status' => $movie->monetization_status ?? 'disabled',
            'submission_origin' => $movie->submission_origin ?? 'administrator',
            'submitted_by' => $movie->submitted_by,
            'cdn_asset_id' => $movie->cdn_asset_id,
            'vj_id' => $movie->vj_id,
            'media_library_id' => $movie->media_library_id,
            'tmdb_id' => $movie->tmdb_id,
            'imdb_id' => $movie->imdb_id,
            'homepage' => $movie->homepage,
            'production_companies' => $movie->production_companies ?? [],
            'production_countries' => $movie->production_countries ?? [],
            'tags' => $movie->tags ?? [],
            'seo_title' => $movie->seo_title,
            'seo_description' => $movie->seo_description,
            'scheduled_for' => $movie->scheduled_for?->toIso8601String(),
            'ownership_declaration_accepted' => (bool) $movie->ownership_declaration_accepted_at,
            'genres' => $movie->relationLoaded('genres')
                ? $movie->genres->map(fn($g) => ['id' => $g->id, 'name' => $g->name])
                : [],
            'actors' => $movie->relationLoaded('actors')
                ? $movie->actors->map(fn ($actor) => [
                    'id' => $actor->id,
                    'name' => $actor->name,
                    'image' => $this->resolveMediaUrl($actor->image),
                    'role' => $actor->pivot?->role,
                    'order' => $actor->pivot?->order,
                ])
                : [],
            'monetization' => $movie->relationLoaded('monetizationSetting') && $movie->monetizationSetting
                ? $this->formatMonetization($movie->monetizationSetting)
                : null,
            'subtitles' => $movie->relationLoaded('subtitles')
                ? $movie->subtitles->map(fn ($subtitle) => [
                    'id' => $subtitle->id,
                    'language' => $subtitle->language,
                    'label' => $subtitle->label,
                    'format' => $subtitle->format,
                    'is_default' => (bool) $subtitle->is_default,
                    'is_active' => (bool) $subtitle->is_active,
                    'url' => $subtitle->full_url,
                ])
                : [],
            'review_history' => $movie->relationLoaded('creatorReviews')
                ? $movie->creatorReviews->map(fn ($review) => [
                    'id' => $review->id,
                    'status' => $review->status,
                    'creator_message' => $review->creator_message,
                    'requested_changes' => $review->requested_changes ?? [],
                    'reviewed_at' => $review->reviewed_at?->toIso8601String(),
                ])
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
            'short_description' => $show->short_description,
            'original_title' => $show->original_title,
            'tagline' => $show->tagline,
            'director' => $show->director,
            'trailer_url' => $show->trailer_url,
            'thumbnail' => $this->resolveMediaUrl($show->thumbnail),
            'backdrop' => $this->resolveMediaUrl($show->backdrop),
            'rating' => $show->rating,
            'release_date' => $show->release_date?->format('Y-m-d'),
            'certificate' => $show->certificate,
            'country' => $show->country,
            'language' => $show->language,
            'original_language' => $show->original_language,
            'translation_language' => $show->translation_language,
            'category_id' => $show->category_id,
            'is_free' => (bool) $show->is_free,
            'is_premium' => (bool) $show->is_premium,
            'price_rent' => $show->price_rent,
            'price_buy' => $show->price_buy,
            'download_enabled' => (bool) $show->download_enabled,
            'is_active' => (bool) $show->is_active,
            'publish_status' => $show->publish_status ?? 'draft',
            'processing_status' => $show->processing_status ?? 'not_started',
            'editorial_status' => $show->editorial_status ?? 'not_submitted',
            'publication_status' => $show->publication_status ?? 'draft',
            'monetization_status' => $show->monetization_status ?? 'disabled',
            'submission_origin' => $show->submission_origin ?? 'administrator',
            'submitted_by' => $show->submitted_by,
            'cdn_asset_id' => $show->cdn_asset_id,
            'vj_id' => $show->vj_id,
            'media_library_id' => $show->media_library_id,
            'tmdb_id' => $show->tmdb_id,
            'imdb_id' => $show->imdb_id,
            'homepage' => $show->homepage,
            'status' => $show->status,
            'networks' => $show->networks ?? [],
            'production_companies' => $show->production_companies ?? [],
            'production_countries' => $show->production_countries ?? [],
            'tags' => $show->tags ?? [],
            'seo_title' => $show->seo_title,
            'seo_description' => $show->seo_description,
            'scheduled_for' => $show->scheduled_for?->toIso8601String(),
            'ownership_declaration_accepted' => (bool) $show->ownership_declaration_accepted_at,
            'number_of_seasons' => $show->number_of_seasons,
            'number_of_episodes' => $show->number_of_episodes,
            'genres' => $show->relationLoaded('genres')
                ? $show->genres->map(fn($g) => ['id' => $g->id, 'name' => $g->name])
                : [],
            'actors' => $show->relationLoaded('actors')
                ? $show->actors->map(fn ($actor) => [
                    'id' => $actor->id,
                    'name' => $actor->name,
                    'image' => $this->resolveMediaUrl($actor->image),
                    'role' => $actor->pivot?->role,
                    'order' => $actor->pivot?->order,
                ])
                : [],
            'monetization' => $show->relationLoaded('monetizationSetting') && $show->monetizationSetting
                ? $this->formatMonetization($show->monetizationSetting)
                : null,
            'subtitles' => $show->relationLoaded('subtitles')
                ? $show->subtitles->map(fn ($subtitle) => [
                    'id' => $subtitle->id,
                    'language' => $subtitle->language,
                    'label' => $subtitle->label,
                    'format' => $subtitle->format,
                    'is_default' => (bool) $subtitle->is_default,
                    'is_active' => (bool) $subtitle->is_active,
                    'url' => $subtitle->full_url,
                ])
                : [],
            'review_history' => $show->relationLoaded('creatorReviews')
                ? $show->creatorReviews->map(fn ($review) => [
                    'id' => $review->id,
                    'status' => $review->status,
                    'creator_message' => $review->creator_message,
                    'requested_changes' => $review->requested_changes ?? [],
                    'reviewed_at' => $review->reviewed_at?->toIso8601String(),
                ])
                : [],
            'views_count' => (int) ($show->views_count ?? 0),
            'created_at' => $show->created_at?->toIso8601String(),
            'updated_at' => $show->updated_at?->toIso8601String(),
        ];
    }

    protected function formatVideoSource(\App\Models\VideoSource $source): array
    {
        $metadata = $source->metadata ?? [];
        $targetKey = $source->storage_target_key
            ?: ($metadata['nbx']['storage_target'] ?? $metadata['storage_target_key'] ?? null);
        $target = $targetKey ? (array) config('storage_targets.targets.'.$targetKey, []) : [];

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
            'storage_target_key' => $targetKey,
            'storage_target_label' => $target['label'] ?? $targetKey,
            'storage_provider' => $target['provider'] ?? null,
            'storage_disk' => $source->storage_disk,
            'storage_bucket' => $source->storage_bucket ?: ($target['bucket'] ?? null),
            'storage_public_url' => $target['public_url'] ?? null,
            'last_message' => $metadata['last_message'] ?? null,
            'source_role' => $metadata['source_role'] ?? null,
            'processing_stage' => $metadata['processing_stage'] ?? $metadata['cdn_status'] ?? $metadata['fetch_status'] ?? null,
            'progress_percent' => $metadata['progress_percent'] ?? $metadata['progress'] ?? $metadata['telebot_progress'] ?? null,
            'output_format' => $metadata['output_format'] ?? $source->format,
            'final_file_size' => $metadata['final_file_size'] ?? $source->file_size,
            'storage_saved_bytes' => $metadata['storage_saved_bytes'] ?? null,
            'warnings' => $metadata['warnings'] ?? [],
            'failure_reason' => $source->failure_reason ?? $metadata['failure_reason'] ?? $metadata['last_error'] ?? null,
            'error_code' => $metadata['error_code'] ?? $metadata['nbx_sync_error_code'] ?? null,
            'support_reference' => $metadata['support_reference'] ?? $metadata['nbx_sync_support_reference'] ?? null,
            'retryable' => (bool) ($metadata['retryable'] ?? false),
            'action_required' => (bool) ($metadata['action_required'] ?? false),
            'created_at' => $source->created_at?->toIso8601String(),
        ];
    }

    protected function formatMonetization(\App\Models\CreatorMonetizationSetting $setting): array
    {
        return [
            'subscription_enabled' => (bool) $setting->subscription_enabled,
            'subscription_plan_ids' => $setting->subscription_plan_ids ?? [],
            'rent_enabled' => (bool) $setting->rent_enabled,
            'rent_price_minor' => $setting->rent_price_minor,
            'rental_duration_hours' => $setting->rental_duration_hours,
            'purchase_enabled' => (bool) $setting->purchase_enabled,
            'purchase_price_minor' => $setting->purchase_price_minor,
            'currency' => $setting->currency,
            'status' => $setting->status,
        ];
    }
}
