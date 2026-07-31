<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations. Change users.plan from enum to varchar so it can store
     * plan names from subscription_plans (Daily Access, Weekly Access, etc.)
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN plan VARCHAR(255) NOT NULL DEFAULT 'FREE'");
        } else {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('plan')->default('FREE')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Map non-enum values back to PRO/ELITE before reverting
        DB::statement("UPDATE users SET plan = CASE 
            WHEN plan IN ('FREE','PRO','ELITE') THEN plan 
            ELSE 'PRO' 
        END WHERE plan NOT IN ('FREE','PRO','ELITE')");
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN plan ENUM('FREE','PRO','ELITE') NOT NULL DEFAULT 'FREE'");
        }
    }
};
