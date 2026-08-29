<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('identity_type', 32)->index();
            $table->string('matching_mode', 32);
            $table->text('pattern');
            $table->text('normalized_pattern')->nullable();
            $table->string('action', 32)->index();
            $table->boolean('enabled')->default(true)->index();
            $table->string('risk_level', 16)->default('HIGH');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->integer('priority')->default(100)->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('security_identities', function (Blueprint $table): void {
            $table->id();
            $table->string('identity_type', 32);
            $table->string('normalized_value', 512);
            $table->string('value_hash', 64);
            $table->string('action', 32)->index();
            $table->boolean('enabled')->default(true)->index();
            $table->string('risk_level', 16)->default('HIGH');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->string('source', 32)->default('MANUAL');
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
            $table->unique(['identity_type', 'value_hash', 'action'], 'security_identities_unique_action');
            $table->index(['identity_type', 'value_hash', 'enabled'], 'security_identities_lookup');
        });

        Schema::create('security_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 64)->index();
            $table->string('risk_level', 16)->default('NORMAL')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('partner_id')->nullable()->index();
            $table->unsignedBigInteger('security_rule_id')->nullable()->index();
            $table->unsignedBigInteger('security_identity_id')->nullable()->index();
            $table->unsignedBigInteger('payment_transaction_id')->nullable()->index();
            $table->string('provider', 32)->nullable();
            $table->string('provider_user_id', 255)->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('device_id', 128)->nullable()->index();
            $table->string('payer_phone', 20)->nullable()->index();
            $table->string('route', 255)->nullable();
            $table->string('action', 64)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();
        });

        Schema::create('protected_payers', function (Blueprint $table): void {
            $table->id();
            $table->string('normalized_phone', 20)->unique();
            $table->string('network', 32)->nullable();
            $table->string('country', 2)->default('UG');
            $table->string('status', 24)->default('ACTIVE')->index();
            $table->string('reason', 64);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unsignedBigInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('provider', 32)->nullable();
            $table->string('provider_user_id', 255)->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('device_id', 128)->nullable()->index();
            $table->string('payer_phone', 20)->nullable()->index();
            $table->string('gateway', 32)->nullable()->index();
            $table->string('payment_type', 24)->nullable()->index();
            $table->boolean('allowed')->default(false)->index();
            $table->string('decision_code', 64)->nullable()->index();
            $table->string('risk_level', 16)->default('NORMAL')->index();
            $table->json('risk_reasons')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();
            $table->index(['user_id', 'occurred_at'], 'payment_attempts_user_time');
            $table->index(['device_id', 'occurred_at'], 'payment_attempts_device_time');
            $table->index(['ip_address', 'occurred_at'], 'payment_attempts_ip_time');
        });

        Schema::create('user_ip_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->index();
            $table->string('activity_type', 32)->index();
            $table->string('device_id', 128)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();
            $table->index(['user_id', 'occurred_at'], 'user_ip_activities_user_time');
        });

        Schema::create('account_enforcements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('action', 32)->index();
            $table->string('previous_status', 24)->nullable();
            $table->string('new_status', 24)->nullable();
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->string('source', 24)->default('MANUAL');
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->unsignedBigInteger('security_event_id')->nullable()->index();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('security_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_settings');
        Schema::dropIfExists('account_enforcements');
        Schema::dropIfExists('user_ip_activities');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('protected_payers');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('security_identities');
        Schema::dropIfExists('security_rules');
    }
};
