<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_gateways')) {
            Schema::table('payment_gateways', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_gateways', 'code')) {
                    $table->string('code')->nullable()->after('slug');
                }
                if (! Schema::hasColumn('payment_gateways', 'logo_path')) {
                    $table->string('logo_path')->nullable()->after('display_name');
                }
                if (! Schema::hasColumn('payment_gateways', 'helper_text')) {
                    $table->string('helper_text')->nullable()->after('description');
                }
            });
        }

        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_transactions', 'gateway_code')) {
                    $table->string('gateway_code')->nullable()->after('payment_gateway_id');
                }
                if (! Schema::hasColumn('payment_transactions', 'external_reference')) {
                    $table->string('external_reference')->nullable()->after('gateway_transaction_id');
                }
                if (! Schema::hasColumn('payment_transactions', 'provider_code')) {
                    $table->string('provider_code')->nullable()->after('external_reference');
                }
                if (! Schema::hasColumn('payment_transactions', 'raw_request')) {
                    $table->json('raw_request')->nullable()->after('gateway_response');
                }
                if (! Schema::hasColumn('payment_transactions', 'raw_response')) {
                    $table->json('raw_response')->nullable()->after('raw_request');
                }
                if (! Schema::hasColumn('payment_transactions', 'raw_callback')) {
                    $table->json('raw_callback')->nullable()->after('raw_response');
                }
                if (! Schema::hasColumn('payment_transactions', 'failure_reason')) {
                    $table->string('failure_reason')->nullable()->after('status');
                }
            });

            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->index(['gateway_code', 'external_reference'], 'pt_gateway_external_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                try {
                    $table->dropIndex('pt_gateway_external_idx');
                } catch (\Throwable $e) {
                    // Ignore when index does not exist.
                }

                $dropColumns = [];
                foreach ([
                    'gateway_code',
                    'external_reference',
                    'provider_code',
                    'raw_request',
                    'raw_response',
                    'raw_callback',
                    'failure_reason',
                ] as $column) {
                    if (Schema::hasColumn('payment_transactions', $column)) {
                        $dropColumns[] = $column;
                    }
                }

                if ($dropColumns !== []) {
                    $table->dropColumn($dropColumns);
                }
            });
        }

        if (Schema::hasTable('payment_gateways')) {
            Schema::table('payment_gateways', function (Blueprint $table) {
                $dropColumns = [];
                foreach (['code', 'logo_path', 'helper_text'] as $column) {
                    if (Schema::hasColumn('payment_gateways', $column)) {
                        $dropColumns[] = $column;
                    }
                }

                if ($dropColumns !== []) {
                    $table->dropColumn($dropColumns);
                }
            });
        }
    }
};
