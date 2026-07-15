<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dmca_notices')) {
            Schema::create('dmca_notices', function (Blueprint $table) {
                $table->id();

                $table->enum('content_type', ['MOVIE', 'TV_SHOW']);
                $table->unsignedBigInteger('content_id');

                $table->string('reference_number')->unique();

                $table->string('complainant_name')->nullable();
                $table->string('complainant_email')->nullable();
                $table->string('represented_rightsholder')->nullable();

                $table->text('claim_description')->nullable();
                $table->string('source')->nullable(); // email|webform|google|manual
                $table->string('affected_url')->nullable();

                $table->timestamp('received_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();

                $table->string('action_taken')->nullable(); // dmca_removed|restored|no_action|etc
                $table->string('status')->default('pending_review'); // pending_review|validated|actioned|rejected|restored|closed

                $table->text('notes')->nullable();
                $table->json('attachments_json')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();

                $table->timestamps();

                $table->index(['content_type', 'content_id']);
                $table->index(['status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dmca_notices');
    }
};

