<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('creator_earnings')) {
            Schema::create('creator_earnings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
                $table->string('earnable_type'); // App\Models\Movie or App\Models\TVShow
                $table->unsignedBigInteger('earnable_id');
                $table->decimal('gross_amount', 12, 2);
                $table->decimal('commission_rate', 5, 2);
                $table->decimal('platform_amount', 12, 2);
                $table->decimal('creator_amount', 12, 2);
                $table->string('status', 20)->default('pending')->comment('pending|available|withdrawn|reversed');
                $table->timestamp('available_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['earnable_type', 'earnable_id']);
                $table->index('available_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_earnings');
    }
};
