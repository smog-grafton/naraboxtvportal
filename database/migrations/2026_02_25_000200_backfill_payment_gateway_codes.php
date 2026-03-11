<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_gateways') || ! Schema::hasColumn('payment_gateways', 'code')) {
            return;
        }

        DB::table('payment_gateways')
            ->whereNull('code')
            ->update(['code' => DB::raw('slug')]);
    }

    public function down(): void
    {
        // No-op. Codes should remain populated.
    }
};

