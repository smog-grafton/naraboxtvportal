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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Daily Access, Weekly Access, Monthly Access
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('duration_days'); // 1, 7, 30
            $table->decimal('price', 10, 2); // 2000, 5000, 8500
            $table->json('features')->nullable(); // Array of features (same for all plans)
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
