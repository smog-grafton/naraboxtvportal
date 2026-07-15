<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('autoplay_next_episode')->default(true);
            $table->string('preferred_subtitle', 32)->nullable();
            $table->boolean('preferred_subtitle_enabled')->default(false);
            $table->string('preferred_quality', 32)->nullable();
            $table->decimal('volume', 4, 2)->default(1);
            $table->boolean('muted')->default(false);
            $table->boolean('theater_mode')->default(false);
            $table->boolean('keyboard_shortcuts_enabled')->default(true);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_preferences');
    }
};
