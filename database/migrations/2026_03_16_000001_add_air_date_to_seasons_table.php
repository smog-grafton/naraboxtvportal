<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            if (!Schema::hasColumn('seasons', 'air_date')) {
                $table->date('air_date')->nullable()->after('description');
            }
        });

        // Make media_id nullable for creator-created seasons (tv_show_id is used instead)
        if (Schema::hasColumn('seasons', 'media_id')) {
            Schema::table('seasons', function (Blueprint $table) {
                $table->dropForeign(['media_id']);
            });
            DB::statement('ALTER TABLE seasons MODIFY media_id BIGINT UNSIGNED NULL');
            Schema::table('seasons', function (Blueprint $table) {
                $table->foreign('media_id')->references('id')->on('movies')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            if (Schema::hasColumn('seasons', 'air_date')) {
                $table->dropColumn('air_date');
            }
        });
    }
};
