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
        // Remove access_type from movies table
        if (Schema::hasColumn('movies', 'access_type')) {
            Schema::table('movies', function (Blueprint $table) {
                $table->dropColumn('access_type');
            });
        }

        // Remove access_type from tv_shows table if it exists
        if (Schema::hasTable('tv_shows') && Schema::hasColumn('tv_shows', 'access_type')) {
            Schema::table('tv_shows', function (Blueprint $table) {
                $table->dropColumn('access_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add access_type back to movies table
        if (!Schema::hasColumn('movies', 'access_type')) {
            Schema::table('movies', function (Blueprint $table) {
                $table->enum('access_type', ['FREE', 'PREMIUM', 'RENT', 'BUY'])->nullable()->after('trending_score');
            });
        }

        // Add access_type back to tv_shows table if it exists
        if (Schema::hasTable('tv_shows') && !Schema::hasColumn('tv_shows', 'access_type')) {
            Schema::table('tv_shows', function (Blueprint $table) {
                $table->enum('access_type', ['FREE', 'PREMIUM', 'RENT', 'BUY'])->nullable()->after('trending_score');
            });
        }
    }
};
