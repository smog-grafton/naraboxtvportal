<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_status', 24)->default('ACTIVE')->index()->after('role_id');
            $table->string('risk_level', 16)->default('NORMAL')->index()->after('account_status');
            $table->text('security_reason')->nullable()->after('risk_level');
            $table->text('security_notes')->nullable()->after('security_reason');
            $table->timestamp('status_started_at')->nullable()->after('security_notes');
            $table->timestamp('status_expires_at')->nullable()->index()->after('status_started_at');
            $table->unsignedBigInteger('status_changed_by')->nullable()->index()->after('status_expires_at');
            $table->string('registration_ip', 45)->nullable()->index()->after('status_changed_by');
            $table->text('registration_user_agent')->nullable()->after('registration_ip');
            $table->string('registration_device_id', 128)->nullable()->index()->after('registration_user_agent');
            $table->string('last_login_ip', 45)->nullable()->index()->after('registration_device_id');
            $table->timestamp('last_login_at')->nullable()->after('last_login_ip');
            $table->string('last_payment_ip', 45)->nullable()->index()->after('last_login_at');
            $table->timestamp('last_activity_at')->nullable()->after('last_payment_ip');
        });

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->string('payer_phone', 20)->nullable()->index()->after('provider_code');
            $table->string('payment_ip', 45)->nullable()->index()->after('payer_phone');
            $table->string('device_id', 128)->nullable()->index()->after('payment_ip');
            $table->string('risk_level', 16)->default('NORMAL')->index()->after('device_id');
            $table->json('security_flags')->nullable()->after('risk_level');
            $table->string('idempotency_key', 128)->nullable()->after('security_flags');
            $table->unique(['user_id', 'idempotency_key'], 'payment_transactions_user_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropUnique('payment_transactions_user_idempotency_unique');
            $table->dropColumn(['payer_phone', 'payment_ip', 'device_id', 'risk_level', 'security_flags', 'idempotency_key']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'account_status', 'risk_level', 'security_reason', 'security_notes',
                'status_started_at', 'status_expires_at', 'status_changed_by',
                'registration_ip', 'registration_user_agent', 'registration_device_id',
                'last_login_ip', 'last_login_at', 'last_payment_ip', 'last_activity_at',
            ]);
        });
    }
};
