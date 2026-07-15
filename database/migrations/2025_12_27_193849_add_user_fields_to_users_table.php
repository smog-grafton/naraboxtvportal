<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Guarded: the base users-table migration already creates these columns
        // directly, same situation as 2025_12_27_181318_add_role_to_users_table.
        // Per-column checks keep this a no-op on fresh installs while remaining
        // safe for older environments that predate that base-migration change.
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'plan')) {
                $table->enum('plan', ['FREE', 'PRO', 'ELITE'])->default('FREE')->after('avatar');
            }
            if (!Schema::hasColumn('users', 'plan_status')) {
                $table->enum('plan_status', ['ACTIVE', 'EXPIRED', 'NONE'])->default('NONE')->after('plan');
            }
            if (!Schema::hasColumn('users', 'renewal_date')) {
                $table->date('renewal_date')->nullable()->after('plan_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = array_filter(
                ['phone', 'avatar', 'plan', 'plan_status', 'renewal_date'],
                fn (string $column) => Schema::hasColumn('users', $column)
            );
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
