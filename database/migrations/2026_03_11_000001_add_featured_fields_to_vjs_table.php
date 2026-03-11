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
        // Add featured flags to vjs table if they don't already exist
        Schema::table('vjs', function (Blueprint $table) {
            if (! Schema::hasColumn('vjs', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('translated_count');
            }
            if (! Schema::hasColumn('vjs', 'featured_order')) {
                $table->integer('featured_order')->nullable()->after('is_featured');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vjs', function (Blueprint $table) {
            if (Schema::hasColumn('vjs', 'featured_order')) {
                $table->dropColumn('featured_order');
            }
            if (Schema::hasColumn('vjs', 'is_featured')) {
                $table->dropColumn('is_featured');
            }
        });
    }
};

