<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('creator_applications') || Schema::hasColumn('creator_applications', 'onboarding_step')) {
            return;
        }

        Schema::table('creator_applications', function (Blueprint $table): void {
            $table->unsignedTinyInteger('onboarding_step')->default(1)->after('application_mode');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('creator_applications') || ! Schema::hasColumn('creator_applications', 'onboarding_step')) {
            return;
        }

        Schema::table('creator_applications', function (Blueprint $table): void {
            $table->dropColumn('onboarding_step');
        });
    }
};
