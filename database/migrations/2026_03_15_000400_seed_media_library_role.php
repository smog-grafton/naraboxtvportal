<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->insertOrIgnore([
            [
                'name' => 'media_library',
                'display_name' => 'Media Library',
                'description' => 'Media studio / distributor creator account',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'media_library')->delete();
    }
};
