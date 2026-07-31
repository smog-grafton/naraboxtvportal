<?php

namespace App\Services;

/**
 * The authoritative creator content-input contract shared by API controllers and
 * the form-options endpoint. Business validation remains in the domain services.
 */
class CreatorContentRules
{
    public function movie(bool $update = false): array
    {
        return array_merge($this->shared($update), [
            'duration' => ['nullable', 'string', 'max:20'],
            'vj_id' => ['nullable', 'integer', 'exists:vjs,id'],
            'tmdb_id' => ['nullable', 'integer'],
            'imdb_id' => ['nullable', 'string', 'max:32'],
            'homepage' => ['nullable', 'url', 'max:2048'],
            'production_companies' => ['nullable', 'array'],
            'production_countries' => ['nullable', 'array'],
            'actors' => ['nullable', 'array', 'max:100'],
            'actors.*.actor_id' => ['required_with:actors', 'integer', 'exists:actors,id'],
            'actors.*.role' => ['nullable', 'string', 'max:255'],
            'actors.*.order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    public function tvShow(bool $update = false): array
    {
        return array_merge($this->shared($update), [
            'tmdb_id' => ['nullable', 'integer'],
            'imdb_id' => ['nullable', 'string', 'max:32'],
            'homepage' => ['nullable', 'url', 'max:2048'],
            'status' => ['nullable', 'string', 'max:50'],
            'networks' => ['nullable', 'array'],
            'production_companies' => ['nullable', 'array'],
            'production_countries' => ['nullable', 'array'],
            'actors' => ['nullable', 'array', 'max:100'],
            'actors.*.actor_id' => ['required_with:actors', 'integer', 'exists:actors,id'],
            'actors.*.role' => ['nullable', 'string', 'max:255'],
            'actors.*.order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    public function season(bool $update = false): array
    {
        return [
            'season_number' => [$update ? 'sometimes' : 'required', 'integer', 'min:1', 'max:1000'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'air_date' => ['nullable', 'date'],
        ];
    }

    public function episode(bool $update = false): array
    {
        return [
            'episode_number' => [$update ? 'sometimes' : 'required', 'integer', 'min:1', 'max:10000'],
            'title' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'duration' => ['nullable', 'string', 'max:20'],
            'release_date' => ['nullable', 'date'],
            'translation_language' => ['nullable', 'string', 'max:100'],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
            'thumbnail_file' => ['nullable', 'image', 'max:5120'],
            'download_enabled' => ['nullable', 'boolean'],
            'scheduled_for' => ['nullable', 'date'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:80'],
        ];
    }

    private function shared(bool $update): array
    {
        return [
            'title' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'original_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'tagline' => ['nullable', 'string', 'max:500'],
            'director' => ['nullable', 'string', 'max:255'],
            'trailer_url' => ['nullable', 'url', 'max:2048'],
            'release_date' => ['nullable', 'date'],
            'certificate' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:100'],
            'original_language' => ['nullable', 'string', 'max:100'],
            'translation_language' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_free' => ['sometimes', 'boolean'],
            'is_premium' => ['sometimes', 'boolean'],
            'price_rent' => ['nullable', 'integer', 'min:0'],
            'price_buy' => ['nullable', 'integer', 'min:0'],
            'download_enabled' => ['nullable', 'boolean'],
            'scheduled_for' => ['nullable', 'date'],
            'genres' => ['nullable', 'array', 'max:30'],
            'genres.*' => ['integer', 'exists:genres,id'],
            'thumbnail' => ['nullable', 'image', 'max:5120'],
            'backdrop' => ['nullable', 'image', 'max:10240'],
            'thumbnail_url' => ['nullable', 'string', 'max:2048'],
            'backdrop_url' => ['nullable', 'string', 'max:2048'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:80'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'ownership_declaration' => ['nullable', 'accepted'],
            'monetization' => ['nullable', 'array'],
            'monetization.subscription_enabled' => ['nullable', 'boolean'],
            'monetization.subscription_plan_ids' => ['nullable', 'array'],
            'monetization.subscription_plan_ids.*' => ['integer'],
            'monetization.rent_enabled' => ['nullable', 'boolean'],
            'monetization.rent_price_minor' => ['nullable', 'integer', 'min:0'],
            'monetization.rental_duration_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'monetization.purchase_enabled' => ['nullable', 'boolean'],
            'monetization.purchase_price_minor' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
