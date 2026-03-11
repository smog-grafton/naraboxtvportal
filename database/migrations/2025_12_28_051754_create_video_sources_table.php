<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_sources', function (Blueprint $table) {
            $table->id();
            $table->morphs('sourceable'); // movie_id/episode_id + sourceable_type
            $table->enum('type', ['local', 'url', 'youtube', 'vimeo', 'fetched'])->default('url');
            $table->string('url')->nullable(); // For URL, YouTube, Vimeo
            $table->string('file_path')->nullable(); // For local and fetched files
            $table->string('quality')->nullable(); // e.g., 480p, 720p, 1080p, 4K
            $table->string('format')->nullable(); // mp4, mkv, webm, etc.
            $table->bigInteger('file_size')->nullable(); // in bytes
            $table->integer('duration_seconds')->nullable();
            $table->boolean('is_primary')->default(false); // Primary playback source
            $table->boolean('is_active')->default(true);
            $table->text('metadata')->nullable(); // JSON for additional data
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_sources');
    }
};
