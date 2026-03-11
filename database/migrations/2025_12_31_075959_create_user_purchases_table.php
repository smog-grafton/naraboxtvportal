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
        Schema::create('user_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->morphs('purchasable'); // Can purchase movies or tv_shows
            $table->foreignId('transaction_id')->nullable()->constrained('payment_transactions')->onDelete('set null');
            $table->dateTime('purchased_at');
            $table->timestamps();

            // Ensure user can't purchase the same item twice
            $table->unique(['user_id', 'purchasable_type', 'purchasable_id'], 'unique_purchase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_purchases');
    }
};
