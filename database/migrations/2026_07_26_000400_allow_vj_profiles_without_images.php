<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vjs') && Schema::hasColumn('vjs', 'image')) {
            Schema::table('vjs', function (Blueprint $table): void {
                $table->string('image')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vjs') && Schema::hasColumn('vjs', 'image')) {
            DB::table('vjs')->whereNull('image')->update(['image' => '']);

            Schema::table('vjs', function (Blueprint $table): void {
                $table->string('image')->nullable(false)->change();
            });
        }
    }
};
