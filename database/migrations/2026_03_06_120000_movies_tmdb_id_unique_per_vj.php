<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow same TMDB movie to exist multiple times for different VJs (translators).
     * Replace unique(tmdb_id) with unique(tmdb_id, vj_id).
     */
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropUnique('movies_tmdb_id_unique');
        });

        Schema::table('movies', function (Blueprint $table) {
            $table->unique(['tmdb_id', 'vj_id'], 'movies_tmdb_id_vj_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropUnique('movies_tmdb_id_vj_id_unique');
        });

        Schema::table('movies', function (Blueprint $table) {
            $table->unique('tmdb_id', 'movies_tmdb_id_unique');
        });
    }
};
