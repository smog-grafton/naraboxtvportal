<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('financial_settings')->count() === 0) {
            DB::table('financial_settings')->insert([
                'commission_rate' => 30.00,
                'creator_hold_days' => 7,
                'min_withdrawal_amount' => 10000.00,
                'auto_payout_enabled' => false,
                'unverified_creator_earns' => false,
                'iotec_disbursement_enabled' => true,
                'pawapay_disbursement_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No destructive down - seed data can remain
    }
};
