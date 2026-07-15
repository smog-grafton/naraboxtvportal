<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('creator_payout_attempts')) {
            Schema::create('creator_payout_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('withdrawal_request_id')->constrained('creator_withdrawal_requests')->cascadeOnDelete();
                $table->string('gateway', 50);
                $table->json('gateway_request')->nullable();
                $table->json('gateway_response')->nullable();
                $table->string('status', 30)->default('pending');
                $table->string('external_id')->nullable();
                $table->timestamp('attempted_at');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('withdrawal_request_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_payout_attempts');
    }
};
