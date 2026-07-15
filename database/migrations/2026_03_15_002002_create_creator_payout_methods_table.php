<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('creator_payout_methods')) {
            Schema::create('creator_payout_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('method_type', 20)->comment('mobile_money|bank');
                $table->string('provider', 50)->nullable()->comment('MTN|Airtel|ioTec bank');
                $table->string('phone_number', 20)->nullable();
                $table->string('account_name')->nullable();
                $table->string('account_number')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('bank_code', 20)->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_verified')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'method_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_payout_methods');
    }
};
