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
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->enum('type', ['AUTOMATIC', 'MANUAL'])->default('AUTOMATIC')->after('slug');
            $table->text('instructions')->nullable()->after('description'); // Rich text instructions for manual gateways
            $table->json('payment_details')->nullable()->after('instructions'); // Phone numbers, bank details, etc.
            // payment_details structure:
            // {
            //   "phone_numbers": [
            //     {"network": "MTN", "number": "078377122"},
            //     {"network": "AIRTEL", "number": "070293354"}
            //   ],
            //   "bank_account": {
            //     "bank_name": "XYZ Bank",
            //     "branch": "Kampala Branch",
            //     "account_number": "123456789",
            //     "account_name": "NaraBox Ltd"
            //   }
            // }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropColumn(['type', 'instructions', 'payment_details']);
        });
    }
};
