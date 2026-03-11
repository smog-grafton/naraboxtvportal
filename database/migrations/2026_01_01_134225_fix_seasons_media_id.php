<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill media_id for existing seasons that don't have it
        // For each season, find or create a corresponding movie entry for its TV show
        $seasons = DB::table('seasons')
            ->whereNull('media_id')
            ->orWhere('media_id', 0)
            ->get();

        foreach ($seasons as $season) {
            $tvShow = DB::table('tv_shows')->where('id', $season->tv_show_id)->first();
            
            if (!$tvShow) {
                continue;
            }

            // Find or create corresponding movie entry
            $movie = DB::table('movies')
                ->where('media_type', 'SERIES')
                ->where('title', $tvShow->title)
                ->first();

            if (!$movie) {
                // Create movie entry
                $movieId = DB::table('movies')->insertGetId([
                    'title' => $tvShow->title,
                    'slug' => $tvShow->slug ?? \Illuminate\Support\Str::slug($tvShow->title),
                    'description' => $tvShow->description,
                    'thumbnail' => $tvShow->thumbnail,
                    'backdrop' => $tvShow->backdrop,
                    'rating' => $tvShow->rating,
                    'release_date' => $tvShow->release_date,
                    'category_id' => $tvShow->category_id,
                    'vj_id' => $tvShow->vj_id,
                    'media_type' => 'SERIES',
                    'access_type' => $tvShow->access_type ?? 'FREE',
                    'is_free' => $tvShow->is_free ?? true,
                    'is_premium' => $tvShow->is_premium ?? false,
                    'price_rent' => $tvShow->price_rent,
                    'price_buy' => $tvShow->price_buy,
                    'is_active' => $tvShow->is_active ?? true,
                    'trending_score' => $tvShow->trending_score ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $movieId = $movie->id;
            }

            // Update season with media_id
            DB::table('seasons')
                ->where('id', $season->id)
                ->update(['media_id' => $movieId]);
        }
    }

    public function down(): void
    {
        // This migration is not reversible as we can't determine which media_id values were set by this migration
    }
};
