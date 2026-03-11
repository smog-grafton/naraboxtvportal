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
        Schema::create('user_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->morphs('rentable'); // Can rent movies or tv_shows
            $table->foreignId('transaction_id')->nullable()->constrained('payment_transactions')->onDelete('set null');
            $table->dateTime('rented_at');
            $table->dateTime('expires_at'); // 30 days from rented_at
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Ensure user can't rent the same item multiple times if active
            $table->unique(['user_id', 'rentable_type', 'rentable_id', 'is_active'], 'unique_active_rental');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_rentals');
    }
};
