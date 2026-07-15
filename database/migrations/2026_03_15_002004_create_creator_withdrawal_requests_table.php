<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('creator_withdrawal_requests')) {
            Schema::create('creator_withdrawal_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('payout_method_id')->nullable()->constrained('creator_payout_methods')->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('status', 30)->default('pending')
                    ->comment('pending|under_review|approved|processing|paid|failed|rejected|cancelled');
                $table->string('reference')->unique();
                $table->timestamp('requested_at');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->text('admin_notes')->nullable();
                $table->string('gateway_used', 50)->nullable();
                $table->string('gateway_reference')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_withdrawal_requests');
    }
};
