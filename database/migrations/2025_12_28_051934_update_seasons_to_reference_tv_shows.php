<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add tv_show_id column to seasons
        Schema::table('seasons', function (Blueprint $table) {
            $table->foreignId('tv_show_id')->nullable()->after('id')->constrained('tv_shows')->onDelete('cascade');
        });

        // Migrate existing data: if media_type is SERIES, create TV show and link seasons
        // This will be handled in a seeder or manual migration
        
        // After migration, we can optionally drop media_id if desired
        // For now, keep both for backward compatibility during transition
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropForeign(['tv_show_id']);
            $table->dropColumn('tv_show_id');
        });
    }
};
