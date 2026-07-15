<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Movies
        if (Schema::hasTable('movies') && ! Schema::hasColumn('movies', 'content_status')) {
            Schema::table('movies', function (Blueprint $table) {
                $table->string('content_status')->default('published')->after('is_active');
            });

            // Backfill for existing rows (defensive)
            DB::table('movies')->whereNull('content_status')->update(['content_status' => 'published']);
        }

        // TV Shows
        if (Schema::hasTable('tv_shows') && ! Schema::hasColumn('tv_shows', 'content_status')) {
            Schema::table('tv_shows', function (Blueprint $table) {
                $table->string('content_status')->default('published')->after('is_active');
            });

            // Backfill for existing rows (defensive)
            DB::table('tv_shows')->whereNull('content_status')->update(['content_status' => 'published']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('movies') && Schema::hasColumn('movies', 'content_status')) {
            Schema::table('movies', function (Blueprint $table) {
                $table->dropColumn('content_status');
            });
        }

        if (Schema::hasTable('tv_shows') && Schema::hasColumn('tv_shows', 'content_status')) {
            Schema::table('tv_shows', function (Blueprint $table) {
                $table->dropColumn('content_status');
            });
        }
    }
};

