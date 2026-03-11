<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Movies table
        Schema::table('movies', function (Blueprint $table) {
            $table->integer('tmdb_id')->nullable()->unique()->after('id');
            $table->string('imdb_id')->nullable()->after('tmdb_id');
            $table->string('original_title')->nullable()->after('title');
            $table->text('tagline')->nullable()->after('description');
            $table->bigInteger('budget')->nullable()->after('duration');
            $table->bigInteger('revenue')->nullable()->after('budget');
            $table->string('status')->nullable()->after('revenue'); // Released, Post Production, etc.
            $table->string('homepage')->nullable()->after('status');
            $table->decimal('popularity', 10, 2)->nullable()->after('rating');
            $table->integer('vote_count')->default(0)->after('popularity');
            $table->json('production_companies')->nullable()->after('country');
            $table->json('production_countries')->nullable()->after('production_companies');
            $table->integer('collection_id')->nullable()->after('production_countries');
        });

        // TV Shows table
        Schema::table('tv_shows', function (Blueprint $table) {
            $table->integer('tmdb_id')->nullable()->unique()->after('id');
            $table->string('imdb_id')->nullable()->after('tmdb_id');
            $table->string('original_title')->nullable()->after('title');
            $table->text('tagline')->nullable()->after('description');
            $table->string('status')->nullable()->after('duration'); // Returning Series, Ended, etc.
            $table->string('homepage')->nullable()->after('status');
            $table->decimal('popularity', 10, 2)->nullable()->after('rating');
            $table->integer('vote_count')->default(0)->after('popularity');
            $table->integer('number_of_seasons')->default(0)->after('vote_count');
            $table->integer('number_of_episodes')->default(0)->after('number_of_seasons');
            $table->json('networks')->nullable()->after('country');
            $table->json('production_companies')->nullable()->after('networks');
            $table->json('production_countries')->nullable()->after('production_companies');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn([
                'tmdb_id', 'imdb_id', 'original_title', 'tagline', 'budget', 'revenue',
                'status', 'homepage', 'popularity', 'vote_count', 'production_companies',
                'production_countries', 'collection_id'
            ]);
        });

        Schema::table('tv_shows', function (Blueprint $table) {
            $table->dropColumn([
                'tmdb_id', 'imdb_id', 'original_title', 'tagline', 'status', 'homepage',
                'popularity', 'vote_count', 'number_of_seasons', 'number_of_episodes',
                'networks', 'production_companies', 'production_countries'
            ]);
        });
    }
};
