<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a `country()` query scope shared by Movie and TVShow. Country data is
 * inconsistent in production (freeform `country` string plus a raw
 * `production_countries` JSON/longtext column), so matching is centralized
 * here against the canonical alias list in config/countries.php rather than
 * scattered across controllers or the frontend.
 */
trait FilterableByCountry
{
    public function scopeCountry(Builder $query, string $key): Builder
    {
        $entry = config('countries.'.$key);

        if (! $entry || empty($entry['match'])) {
            // Unknown country key: no results rather than an error or an
            // accidentally-unfiltered query.
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($entry) {
            foreach ($entry['match'] as $term) {
                $term = strtolower($term);

                if (strlen($term) <= 3) {
                    // Short ISO-style codes (e.g. "in", "kr") are only matched
                    // exactly, to avoid false positives from substring search.
                    $q->orWhereRaw('LOWER(country) = ?', [$term])
                        ->orWhereRaw('LOWER(production_countries) LIKE ?', ['%"'.$term.'"%']);
                } else {
                    $q->orWhereRaw('LOWER(country) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(production_countries) LIKE ?', ['%'.$term.'%']);
                }
            }
        });
    }
}
