<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('creator_withdrawal_allocations')) {
            Schema::create('creator_withdrawal_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('withdrawal_request_id')->constrained('creator_withdrawal_requests')->cascadeOnDelete();
                $table->foreignId('creator_earning_id')->constrained('creator_earnings')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->timestamps();

                $table->index('withdrawal_request_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_withdrawal_allocations');
    }
};
