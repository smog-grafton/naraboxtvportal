<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->timestamp('access_granted_at')->nullable()->index()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', fn (Blueprint $table) => $table->dropColumn('access_granted_at'));
    }
};
