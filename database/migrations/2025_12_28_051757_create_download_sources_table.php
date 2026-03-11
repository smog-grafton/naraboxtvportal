<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_sources', function (Blueprint $table) {
            $table->id();
            $table->morphs('downloadable'); // movie_id/episode_id + downloadable_type
            $table->enum('type', ['local', 'url', 'fetched'])->default('url');
            $table->string('url')->nullable(); // External download URL
            $table->string('file_path')->nullable(); // Local file path
            $table->string('quality')->required(); // 480p, 720p, 1080p, 4K
            $table->string('format')->required(); // mp4, mkv, webm, etc.
            $table->bigInteger('file_size')->nullable(); // in bytes
            $table->string('label')->nullable(); // e.g., "1080p MP4", "4K MKV"
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_sources');
    }
};
