<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Movies table
        Schema::table('movies', function (Blueprint $table) {
            if (!Schema::hasColumn('movies', 'media_library_id')) {
                $table->foreignId('media_library_id')
                    ->nullable()
                    ->constrained('media_libraries')
                    ->nullOnDelete()
                    ->after('vj_id');
            }
            if (!Schema::hasColumn('movies', 'cdn_asset_id')) {
                $table->uuid('cdn_asset_id')->nullable()->index()->after('media_library_id');
            }
            if (!Schema::hasColumn('movies', 'publish_status')) {
                $table->enum('publish_status', ['draft', 'pending_review', 'published', 'rejected'])
                    ->default('published')
                    ->after('cdn_asset_id');
            }
        });

        // TV Shows table
        Schema::table('tv_shows', function (Blueprint $table) {
            if (!Schema::hasColumn('tv_shows', 'media_library_id')) {
                $table->foreignId('media_library_id')
                    ->nullable()
                    ->constrained('media_libraries')
                    ->nullOnDelete()
                    ->after('id');
            }
            if (!Schema::hasColumn('tv_shows', 'vj_id')) {
                $table->foreignId('vj_id')
                    ->nullable()
                    ->constrained('vjs')
                    ->nullOnDelete()
                    ->after('media_library_id');
            }
            if (!Schema::hasColumn('tv_shows', 'cdn_asset_id')) {
                $table->uuid('cdn_asset_id')->nullable()->index()->after('vj_id');
            }
            if (!Schema::hasColumn('tv_shows', 'publish_status')) {
                $table->enum('publish_status', ['draft', 'pending_review', 'published', 'rejected'])
                    ->default('published')
                    ->after('cdn_asset_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            if (Schema::hasColumn('movies', 'publish_status')) {
                $table->dropColumn('publish_status');
            }
            if (Schema::hasColumn('movies', 'cdn_asset_id')) {
                $table->dropColumn('cdn_asset_id');
            }
            if (Schema::hasColumn('movies', 'media_library_id')) {
                $table->dropForeign(['media_library_id']);
                $table->dropColumn('media_library_id');
            }
        });

        Schema::table('tv_shows', function (Blueprint $table) {
            if (Schema::hasColumn('tv_shows', 'publish_status')) {
                $table->dropColumn('publish_status');
            }
            if (Schema::hasColumn('tv_shows', 'cdn_asset_id')) {
                $table->dropColumn('cdn_asset_id');
            }
            if (Schema::hasColumn('tv_shows', 'vj_id')) {
                $table->dropForeign(['vj_id']);
                $table->dropColumn('vj_id');
            }
            if (Schema::hasColumn('tv_shows', 'media_library_id')) {
                $table->dropForeign(['media_library_id']);
                $table->dropColumn('media_library_id');
            }
        });
    }
};
