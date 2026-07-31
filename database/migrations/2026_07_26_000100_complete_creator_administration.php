<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $requiredTables = [
            'creator_permissions',
            'financial_settings',
            'creator_wallets',
            'creator_settlements',
            'user_notifications',
        ];
        $missingTables = array_values(array_filter(
            $requiredTables,
            fn (string $table): bool => ! Schema::hasTable($table)
        ));
        if ($missingTables !== []) {
            throw new RuntimeException(
                'Creator administration foundation is missing: '.implode(', ', $missingTables)
                .'. Deploy and run the 2026_07_25 creator platform migrations first.'
            );
        }

        Schema::table('creator_permissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('creator_permissions', 'permission_preset')) {
                $table->string('permission_preset', 40)->default('standard')->after('creator_application_id');
            }
            if (! Schema::hasColumn('creator_permissions', 'capabilities')) {
                $table->json('capabilities')->nullable()->after('permission_preset');
            }
        });

        Schema::table('financial_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('financial_settings', 'currency')) {
                $table->char('currency', 3)->default('UGX')->after('id');
            }
            if (! Schema::hasColumn('financial_settings', 'settlement_frequency')) {
                $table->string('settlement_frequency', 30)->default('monthly')->after('subscription_pool_bps');
            }
            if (! Schema::hasColumn('financial_settings', 'manual_payout_enabled')) {
                $table->boolean('manual_payout_enabled')->default(true)->after('auto_payout_enabled');
            }
            if (! Schema::hasColumn('financial_settings', 'earnings_pending_until_approval')) {
                $table->boolean('earnings_pending_until_approval')->default(true)->after('unverified_creator_earns');
            }
            if (! Schema::hasColumn('financial_settings', 'unverified_earnings_policy')) {
                $table->string('unverified_earnings_policy', 30)->default('platform_retains')->after('earnings_pending_until_approval');
            }
            if (! Schema::hasColumn('financial_settings', 'refund_chargeback_policy')) {
                $table->text('refund_chargeback_policy')->nullable()->after('unverified_earnings_policy');
            }
        });

        if (! Schema::hasTable('creator_payout_options')) {
            Schema::create('creator_payout_options', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 50)->unique();
                $table->string('label');
                $table->string('category', 40);
                $table->string('automation_type', 30)->default('manual');
                $table->string('adapter', 60)->nullable();
                $table->json('supported_countries')->nullable();
                $table->json('supported_currencies')->nullable();
                $table->bigInteger('minimum_amount_minor')->nullable();
                $table->bigInteger('maximum_amount_minor')->nullable();
                $table->bigInteger('fee_minor')->default(0);
                $table->boolean('is_enabled')->default(false);
                $table->boolean('is_configured')->default(false);
                $table->string('configuration_status', 30)->default('not_required');
                $table->text('configuration_notes')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['is_enabled', 'sort_order']);
            });
        }

        Schema::table('creator_wallets', function (Blueprint $table): void {
            if (! Schema::hasColumn('creator_wallets', 'hold_reason')) {
                $table->text('hold_reason')->nullable()->after('status');
            }
            if (! Schema::hasColumn('creator_wallets', 'held_at')) {
                $table->timestamp('held_at')->nullable()->after('hold_reason');
            }
            if (! Schema::hasColumn('creator_wallets', 'held_by')) {
                $table->foreignId('held_by')->nullable()->after('held_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('creator_settlements', function (Blueprint $table): void {
            if (! Schema::hasColumn('creator_settlements', 'settlement_type')) {
                $table->string('settlement_type', 30)->default('subscription_pool')->after('period');
            }
            if (! Schema::hasColumn('creator_settlements', 'creator_count')) {
                $table->unsignedInteger('creator_count')->default(0)->after('total_qualified_metric');
            }
            if (! Schema::hasColumn('creator_settlements', 'allocated_minor')) {
                $table->bigInteger('allocated_minor')->default(0)->after('creator_count');
            }
            if (! Schema::hasColumn('creator_settlements', 'discrepancy_minor')) {
                $table->bigInteger('discrepancy_minor')->default(0)->after('allocated_minor');
            }
            if (! Schema::hasColumn('creator_settlements', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('calculation_snapshot');
            }
            if (! Schema::hasColumn('creator_settlements', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('admin_notes');
            }
            if (! Schema::hasColumn('creator_settlements', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('failure_reason');
            }
            if (! Schema::hasColumn('creator_settlements', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('creator_notification_deliveries')) {
            Schema::create('creator_notification_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_notification_id')->nullable()->constrained('user_notifications')->nullOnDelete();
                $table->string('template_name', 100);
                $table->string('event_key', 191)->unique();
                $table->string('email_status', 30)->default('pending');
                $table->timestamp('email_sent_at')->nullable();
                $table->text('email_error')->nullable();
                $table->json('safe_metadata')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'template_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_notification_deliveries');

        Schema::table('creator_settlements', function (Blueprint $table): void {
            if (Schema::hasColumn('creator_settlements', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }
            foreach (['cancelled_at', 'failure_reason', 'admin_notes', 'discrepancy_minor', 'allocated_minor', 'creator_count', 'settlement_type'] as $column) {
                if (Schema::hasColumn('creator_settlements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('creator_wallets', function (Blueprint $table): void {
            if (Schema::hasColumn('creator_wallets', 'held_by')) {
                $table->dropConstrainedForeignId('held_by');
            }
            foreach (['held_at', 'hold_reason'] as $column) {
                if (Schema::hasColumn('creator_wallets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('creator_payout_options');

        Schema::table('financial_settings', function (Blueprint $table): void {
            foreach (['refund_chargeback_policy', 'unverified_earnings_policy', 'earnings_pending_until_approval', 'manual_payout_enabled', 'settlement_frequency', 'currency'] as $column) {
                if (Schema::hasColumn('financial_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('creator_permissions', function (Blueprint $table): void {
            foreach (['capabilities', 'permission_preset'] as $column) {
                if (Schema::hasColumn('creator_permissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
