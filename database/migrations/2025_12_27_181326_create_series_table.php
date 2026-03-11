<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Series are stored in movies table with media_type = 'SERIES'
        // This migration creates pivot table for movie-genre relationships
        Schema::create('media_genre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('movies')->onDelete('cascade');
            $table->foreignId('genre_id')->constrained('genres')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['media_id', 'genre_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_genre');
    }
};
