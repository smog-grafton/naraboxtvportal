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
        // Update movies table
        Schema::table('movies', function (Blueprint $table) {
            $table->boolean('is_free')->default(false)->after('access_type');
            $table->boolean('is_premium')->default(false)->after('is_free');
            // Make access_type nullable since we'll use is_free, is_premium, price_rent, price_buy to determine access
            $table->enum('access_type', ['FREE', 'PREMIUM', 'RENT', 'BUY'])->nullable()->change();
        });

        // Update tv_shows table
        Schema::table('tv_shows', function (Blueprint $table) {
            $table->boolean('is_free')->default(false)->after('access_type');
            $table->boolean('is_premium')->default(false)->after('is_free');
            // Make access_type nullable since we'll use is_free, is_premium, price_rent, price_buy to determine access
            $table->enum('access_type', ['FREE', 'PREMIUM', 'RENT', 'BUY'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn(['is_free', 'is_premium']);
            $table->enum('access_type', ['FREE', 'PREMIUM', 'RENT', 'BUY'])->default('FREE')->change();
        });

        Schema::table('tv_shows', function (Blueprint $table) {
            $table->dropColumn(['is_free', 'is_premium']);
            $table->enum('access_type', ['FREE', 'PREMIUM', 'RENT', 'BUY'])->default('FREE')->change();
        });
    }
};
