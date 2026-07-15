<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE video_sources MODIFY type VARCHAR(50) NOT NULL DEFAULT 'url'");
        DB::statement("ALTER TABLE download_sources MODIFY type VARCHAR(50) NOT NULL DEFAULT 'url'");
    }

    public function down(): void
    {
        DB::table('video_sources')->where('type', 'bunny_stream')->update(['type' => 'url']);
        DB::table('download_sources')->where('type', 'bunny_stream')->update(['type' => 'url']);

        DB::statement("ALTER TABLE video_sources MODIFY type ENUM('local', 'url', 'youtube', 'vimeo', 'fetched') NOT NULL DEFAULT 'url'");
        DB::statement("ALTER TABLE download_sources MODIFY type ENUM('local', 'url', 'fetched') NOT NULL DEFAULT 'url'");
    }
};
