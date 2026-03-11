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
        Schema::table('payment_transactions', function (Blueprint $table) {
            // Check if media_id column exists before trying to drop it
            if (Schema::hasColumn('payment_transactions', 'media_id')) {
                // Try to drop foreign key if it exists
                try {
                    $table->dropForeign(['media_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
                $table->dropColumn('media_id');
            }
            
            // Add morphs for rentable/purchasable (movies, tv_shows) if they don't exist
            if (!Schema::hasColumn('payment_transactions', 'transactionable_type')) {
                $table->nullableMorphs('transactionable');
            }
            
            // Add subscription_plan_id for subscription transactions if it doesn't exist
            if (!Schema::hasColumn('payment_transactions', 'subscription_plan_id')) {
                $table->foreignId('subscription_plan_id')->nullable()->after('type')->constrained('subscription_plans')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropMorphs('transactionable');
            $table->dropForeign(['subscription_plan_id']);
            $table->dropColumn('subscription_plan_id');
            
            $table->foreignId('media_id')->nullable()->constrained('movies')->onDelete('set null');
        });
    }
};
