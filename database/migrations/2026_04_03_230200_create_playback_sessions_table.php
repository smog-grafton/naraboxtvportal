<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playback_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_uuid', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('media_id');
            $table->string('media_type', 16);
            $table->foreignId('episode_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_type', 32)->nullable();
            $table->json('preferences')->nullable();
            $table->json('quality_history')->nullable();
            $table->json('error_log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('exit_reason', 32)->nullable();
            $table->unsignedInteger('startup_ms')->nullable();
            $table->unsignedInteger('total_watch_seconds')->default(0);
            $table->decimal('max_position_seconds', 10, 2)->default(0);
            $table->unsignedInteger('buffer_count')->default(0);
            $table->unsignedInteger('total_buffer_ms')->default(0);
            $table->unsignedInteger('quality_switch_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->string('last_quality', 32)->nullable();
            $table->timestamps();

            $table->index(['media_id', 'media_type']);
            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playback_sessions');
    }
};
