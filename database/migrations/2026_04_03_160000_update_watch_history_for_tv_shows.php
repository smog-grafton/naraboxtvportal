<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('watch_history', 'media_type')) {
            Schema::table('watch_history', function (Blueprint $table) {
                $table->string('media_type', 16)->default('MOVIE')->after('media_id');
            });
        }

        $mediaForeign = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'watch_history'
              AND COLUMN_NAME = 'media_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");

        if ($mediaForeign?->CONSTRAINT_NAME) {
            DB::statement(sprintf(
                'ALTER TABLE `watch_history` DROP FOREIGN KEY `%s`',
                $mediaForeign->CONSTRAINT_NAME
            ));
        }

        $indexes = collect(DB::select("SHOW INDEX FROM `watch_history`"))
            ->pluck('Key_name')
            ->unique()
            ->values();

        if (! $indexes->contains('watch_history_user_id_index')) {
            Schema::table('watch_history', function (Blueprint $table) {
                $table->index('user_id', 'watch_history_user_id_index');
            });
        }

        $indexes = collect(DB::select("SHOW INDEX FROM `watch_history`"))
            ->pluck('Key_name')
            ->unique()
            ->values();

        if ($indexes->contains('watch_history_user_id_media_id_episode_id_unique')) {
            Schema::table('watch_history', function (Blueprint $table) {
                $table->dropUnique('watch_history_user_id_media_id_episode_id_unique');
            });
        }

        $indexes = collect(DB::select("SHOW INDEX FROM `watch_history`"))
            ->pluck('Key_name')
            ->unique()
            ->values();

        if (! $indexes->contains('watch_history_user_media_type_episode_unique')) {
            Schema::table('watch_history', function (Blueprint $table) {
                $table->unique(['user_id', 'media_id', 'media_type', 'episode_id'], 'watch_history_user_media_type_episode_unique');
            });
        }
    }

    public function down(): void
    {
        $indexes = collect(DB::select("SHOW INDEX FROM `watch_history`"))
            ->pluck('Key_name')
            ->unique()
            ->values();

        if ($indexes->contains('watch_history_user_media_type_episode_unique')) {
            Schema::table('watch_history', function (Blueprint $table) {
                $table->dropUnique('watch_history_user_media_type_episode_unique');
            });
        }

        $indexes = collect(DB::select("SHOW INDEX FROM `watch_history`"))
            ->pluck('Key_name')
            ->unique()
            ->values();

        if (! $indexes->contains('watch_history_user_id_media_id_episode_id_unique')) {
            Schema::table('watch_history', function (Blueprint $table) {
                $table->unique(['user_id', 'media_id', 'episode_id'], 'watch_history_user_id_media_id_episode_id_unique');
            });
        }

        $mediaForeign = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'watch_history'
              AND COLUMN_NAME = 'media_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");

        if (! $mediaForeign?->CONSTRAINT_NAME) {
            Schema::table('watch_history', function (Blueprint $table) {
                $table->foreign('media_id')->references('id')->on('movies')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('watch_history', 'media_type')) {
            Schema::table('watch_history', function (Blueprint $table) {
                $table->dropColumn('media_type');
            });
        }
    }
};
