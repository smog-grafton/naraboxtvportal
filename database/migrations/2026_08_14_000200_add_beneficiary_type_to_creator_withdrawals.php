<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('creator_withdrawal_requests', 'beneficiary_type')) {
            Schema::table('creator_withdrawal_requests', function (Blueprint $table): void {
                $table->string('beneficiary_type', 24)->default('creator')->after('user_id');
                $table->index(['user_id', 'beneficiary_type', 'status'], 'withdrawal_beneficiary_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('creator_withdrawal_requests', 'beneficiary_type')) {
            Schema::table('creator_withdrawal_requests', function (Blueprint $table): void {
                $table->dropIndex('withdrawal_beneficiary_status_idx');
                $table->dropColumn('beneficiary_type');
            });
        }
    }
};
