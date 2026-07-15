<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add is_verified to vjs if not exists
        if (Schema::hasTable('vjs') && !Schema::hasColumn('vjs', 'is_verified')) {
            Schema::table('vjs', function (Blueprint $table) {
                $table->boolean('is_verified')->default(false)->after('user_id');
            });
        }

        // Create category_tv_show pivot for multiple categories per TV show
        if (!Schema::hasTable('category_tv_show')) {
            Schema::create('category_tv_show', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tv_show_id')->constrained('tv_shows')->onDelete('cascade');
                $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
                $table->timestamps();
                $table->unique(['tv_show_id', 'category_id']);
            });
        }

        // Migrate existing category_id to pivot so TV shows can have multiple categories
        if (Schema::hasTable('tv_shows') && Schema::hasColumn('tv_shows', 'category_id') && Schema::hasTable('category_tv_show')) {
            $rows = DB::table('tv_shows')->whereNotNull('category_id')->select('id', 'category_id')->get();
            foreach ($rows as $row) {
                $exists = DB::table('category_tv_show')
                    ->where('tv_show_id', $row->id)
                    ->where('category_id', $row->category_id)
                    ->exists();
                if (!$exists) {
                    DB::table('category_tv_show')->insert([
                        'tv_show_id' => $row->id,
                        'category_id' => $row->category_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('category_tv_show')) {
            Schema::dropIfExists('category_tv_show');
        }

        if (Schema::hasTable('vjs') && Schema::hasColumn('vjs', 'is_verified')) {
            Schema::table('vjs', function (Blueprint $table) {
                $table->dropColumn('is_verified');
            });
        }
    }
};
