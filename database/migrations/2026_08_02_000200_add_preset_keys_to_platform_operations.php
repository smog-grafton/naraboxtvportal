<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_status_messages', function (Blueprint $table): void {
            $table->string('preset_key', 80)->nullable()->unique()->after('id');
        });

        Schema::table('maintenance_windows', function (Blueprint $table): void {
            $table->string('preset_key', 80)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_windows', function (Blueprint $table): void {
            $table->dropUnique(['preset_key']);
            $table->dropColumn('preset_key');
        });

        Schema::table('system_status_messages', function (Blueprint $table): void {
            $table->dropUnique(['preset_key']);
            $table->dropColumn('preset_key');
        });
    }
};
