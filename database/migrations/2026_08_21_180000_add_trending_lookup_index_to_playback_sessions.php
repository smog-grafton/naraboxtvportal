<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playback_sessions', function (Blueprint $table): void {
            $table->index(
                ['media_type', 'started_at', 'media_id'],
                'playback_sessions_trending_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('playback_sessions', function (Blueprint $table): void {
            $table->dropIndex('playback_sessions_trending_lookup_index');
        });
    }
};
