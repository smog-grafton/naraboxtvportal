<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_status_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->string('message', 280);
            $table->text('details')->nullable();
            $table->string('severity', 24)->default('advisory')->index();
            $table->json('platforms')->nullable();
            $table->json('affected_services')->nullable();
            $table->timestampTz('starts_at')->nullable()->index();
            $table->timestampTz('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_dismissible')->default(true);
            $table->boolean('show_on_home')->default(true);
            $table->boolean('show_globally')->default(false);
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_url', 1024)->nullable();
            $table->unsignedSmallInteger('priority')->default(0)->index();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('maintenance_windows', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 120);
            $table->string('message', 280);
            $table->text('details')->nullable();
            $table->string('mode', 24)->default('partial')->index();
            $table->json('platforms')->nullable();
            $table->json('affected_features')->nullable();
            $table->json('available_features')->nullable();
            $table->timestampTz('starts_at')->nullable()->index();
            $table->timestampTz('ends_at')->nullable()->index();
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('allow_read_only')->default(false);
            $table->unsignedSmallInteger('priority')->default(0)->index();
            $table->unsignedInteger('revision')->default(1);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('platform_version_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('platform', 32)->unique();
            $table->string('latest_version', 32);
            $table->unsignedBigInteger('latest_build');
            $table->string('minimum_version', 32);
            $table->unsignedBigInteger('minimum_build');
            $table->string('update_type', 24)->default('optional');
            $table->string('update_url', 1024)->nullable();
            $table->string('title', 120)->nullable();
            $table->string('message', 280)->nullable();
            $table->text('release_notes')->nullable();
            $table->timestampTz('effective_at')->nullable()->index();
            $table->timestampTz('grace_period_ends_at')->nullable();
            $table->boolean('prompt_enabled')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('operational_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80)->index();
            $table->string('auditable_type');
            $table->string('auditable_id', 64);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id'], 'operational_audit_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_audit_logs');
        Schema::dropIfExists('platform_version_policies');
        Schema::dropIfExists('maintenance_windows');
        Schema::dropIfExists('system_status_messages');
    }
};
