<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('financial_settings')) {
            Schema::create('financial_settings', function (Blueprint $table) {
                $table->id();
                $table->decimal('commission_rate', 5, 2)->default(30.00)->comment('Platform commission %');
                $table->unsignedInteger('creator_hold_days')->default(7)->comment('Days before earnings become available');
                $table->decimal('min_withdrawal_amount', 12, 2)->default(10000.00)->comment('Min UGX per withdrawal');
                $table->boolean('auto_payout_enabled')->default(false);
                $table->boolean('unverified_creator_earns')->default(false)->comment('If true, unverified creators get share');
                $table->boolean('iotec_disbursement_enabled')->default(true);
                $table->boolean('pawapay_disbursement_enabled')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_settings');
    }
};
