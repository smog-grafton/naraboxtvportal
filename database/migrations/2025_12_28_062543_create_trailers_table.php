<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trailers', function (Blueprint $table) {
            $table->id();
            $table->morphs('trailerable'); // movie_id/tv_show_id + trailerable_type
            $table->string('tmdb_id')->nullable();
            $table->string('key'); // YouTube video key
            $table->string('name');
            $table->string('site')->default('YouTube'); // YouTube, Vimeo
            $table->string('type')->default('Trailer'); // Trailer, Teaser, Clip
            $table->integer('size')->nullable(); // 360, 480, 720, 1080
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trailers');
    }
};
