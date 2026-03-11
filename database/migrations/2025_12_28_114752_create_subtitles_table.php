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
        Schema::create('subtitles', function (Blueprint $table) {
            $table->id();
            $table->morphs('subtitleable'); // subtitleable_type, subtitleable_id (for Movie or Episode)
            $table->string('language', 10)->default('en'); // Language code (en, es, fr, etc.)
            $table->string('label')->nullable(); // Display label (e.g., "English", "English (CC)")
            $table->enum('type', ['upload', 'fetched'])->default('upload'); // Upload or fetched from URL
            $table->string('file_path')->nullable(); // Local file path for uploaded/fetched subtitles
            $table->string('url')->nullable(); // Remote URL for fetched subtitles (before fetching)
            $table->string('format', 10)->default('vtt'); // Subtitle format (vtt, srt, ass, etc.)
            $table->boolean('is_default')->default(false); // Default subtitle track
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0); // Order for display
            $table->timestamps();

            // Indexes (morphs() already creates an index, so we don't need to add it again)
            $table->index('language');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subtitles');
    }
};
