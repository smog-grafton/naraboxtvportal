<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('creator_applications')) {
            Schema::create('creator_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->enum('creator_type', ['vj', 'media_library']);
                $table->string('display_name');
                $table->text('bio')->nullable();
                $table->string('profile_image')->nullable();
                $table->json('genres')->nullable();
                $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'needs_changes'])->default('pending');
                $table->text('rejection_reason')->nullable();
                $table->text('admin_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_applications');
    }
};
