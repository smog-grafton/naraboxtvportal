<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->boolean('download_enabled')->default(true)->after('is_active');
        });

        Schema::table('episodes', function (Blueprint $table) {
            $table->boolean('download_enabled')->default(true)->after('video_url');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('download_enabled');
        });

        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumn('download_enabled');
        });
    }
};
