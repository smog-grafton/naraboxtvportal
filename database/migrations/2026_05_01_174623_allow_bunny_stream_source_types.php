<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('video_sources', function (Blueprint $table): void {
                $table->string('type', 50)->default('url')->change();
            });
            Schema::table('download_sources', function (Blueprint $table): void {
                $table->string('type', 50)->default('url')->change();
            });

            return;
        }

        DB::statement("ALTER TABLE video_sources MODIFY type VARCHAR(50) NOT NULL DEFAULT 'url'");
        DB::statement("ALTER TABLE download_sources MODIFY type VARCHAR(50) NOT NULL DEFAULT 'url'");
    }

    public function down(): void
    {
        DB::table('video_sources')->where('type', 'bunny_stream')->update(['type' => 'url']);
        DB::table('download_sources')->where('type', 'bunny_stream')->update(['type' => 'url']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE video_sources MODIFY type ENUM('local', 'url', 'youtube', 'vimeo', 'fetched') NOT NULL DEFAULT 'url'");
            DB::statement("ALTER TABLE download_sources MODIFY type ENUM('local', 'url', 'fetched') NOT NULL DEFAULT 'url'");
        }
    }
};
