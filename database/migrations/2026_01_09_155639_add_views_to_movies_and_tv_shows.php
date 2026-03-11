<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('movies', 'views_count')) {
            Schema::table('movies', function (Blueprint $table) {
                $table->unsignedBigInteger('views_count')->default(0)->after('trending_score');
            });
        }
        
        if (!Schema::hasColumn('movies', 'manual_views')) {
            Schema::table('movies', function (Blueprint $table) {
                $table->unsignedBigInteger('manual_views')->default(0)->after('views_count');
            });
        }

        // Check if tv_shows table exists (might be using movies table with media_type)
        if (Schema::hasTable('tv_shows')) {
            if (!Schema::hasColumn('tv_shows', 'views_count')) {
                Schema::table('tv_shows', function (Blueprint $table) {
                    $table->unsignedBigInteger('views_count')->default(0)->after('trending_score');
                });
            }
            
            if (!Schema::hasColumn('tv_shows', 'manual_views')) {
                Schema::table('tv_shows', function (Blueprint $table) {
                    $table->unsignedBigInteger('manual_views')->default(0)->after('views_count');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('movies', 'views_count')) {
            Schema::table('movies', function (Blueprint $table) {
                $table->dropColumn('views_count');
            });
        }
        
        if (Schema::hasColumn('movies', 'manual_views')) {
            Schema::table('movies', function (Blueprint $table) {
                $table->dropColumn('manual_views');
            });
        }

        if (Schema::hasTable('tv_shows')) {
            if (Schema::hasColumn('tv_shows', 'views_count')) {
                Schema::table('tv_shows', function (Blueprint $table) {
                    $table->dropColumn('views_count');
                });
            }
            
            if (Schema::hasColumn('tv_shows', 'manual_views')) {
                Schema::table('tv_shows', function (Blueprint $table) {
                    $table->dropColumn('manual_views');
                });
            }
        }
    }
};
