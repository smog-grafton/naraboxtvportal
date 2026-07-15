<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_identifier')->unique();
            $table->string('name')->nullable();
            $table->string('platform', 64)->nullable();
            $table->string('app_version', 64)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->text('last_user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('tv_device_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tv_device_id')->nullable()->constrained('tv_devices')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_code', 12)->unique();
            $table->string('device_code_hash', 64)->unique();
            $table->string('status', 32)->default('PENDING');
            $table->timestamp('expires_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->string('issued_ip', 45)->nullable();
            $table->text('issued_user_agent')->nullable();
            $table->string('approved_ip', 45)->nullable();
            $table->text('approved_user_agent')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('tv_checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tv_device_id')->nullable()->constrained('tv_devices')->nullOnDelete();
            $table->string('uuid')->unique();
            $table->string('status', 32)->default('PENDING');
            $table->string('type', 32);
            $table->string('media_type', 32)->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('transaction_ref')->nullable()->index();
            $table->timestamp('expires_at');
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_checkout_sessions');
        Schema::dropIfExists('tv_device_codes');
        Schema::dropIfExists('tv_devices');
    }
};
