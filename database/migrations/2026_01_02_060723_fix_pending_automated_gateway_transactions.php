<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // One-off production data repair (MySQL-only raw SQL: INNER JOIN UPDATE + DATE_SUB).
        // No-op on other drivers (e.g. sqlite in tests) — a fresh test DB has no stale
        // pending transactions to repair, so skipping here changes no runtime behavior.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Mark old PENDING automated gateway transactions as FAILED
        // Only transactions older than 1 hour are marked as failed
        // This prevents marking transactions that are legitimately still processing
        DB::statement("
            UPDATE payment_transactions pt
            INNER JOIN payment_gateways pg ON pt.payment_gateway_id = pg.id
            SET pt.status = 'FAILED',
                pt.gateway_response = JSON_SET(
                    COALESCE(pt.gateway_response, '{}'),
                    '$.auto_failed_reason',
                    'Transaction was pending for automated gateway and marked as failed after timeout'
                )
            WHERE pt.status = 'PENDING'
            AND pg.type = 'AUTOMATIC'
            AND pt.created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse this migration as we don't know which transactions were actually pending vs failed
        // This is a one-way migration
    }
};
