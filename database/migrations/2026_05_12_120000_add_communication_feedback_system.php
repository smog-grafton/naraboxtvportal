<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'marketing_emails_enabled')) {
                $table->boolean('marketing_emails_enabled')->default(true)->after('email_verified_at');
            }

            if (! Schema::hasColumn('users', 'marketing_opt_in_token')) {
                $table->string('marketing_opt_in_token', 64)->nullable()->unique()->after('marketing_emails_enabled');
            }
        });

        Schema::table('email_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('email_templates', 'preheader')) {
                $table->string('preheader')->nullable()->after('subject');
            }

            if (! Schema::hasColumn('email_templates', 'preview_text')) {
                $table->string('preview_text')->nullable()->after('preheader');
            }

            if (! Schema::hasColumn('email_templates', 'template_type')) {
                $table->string('template_type', 32)->default('transactional')->after('preview_text');
            }
        });

        Schema::create('communication_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 24)->default('email');
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('draft');
            $table->string('audience_type', 64)->default('selected_users');
            $table->boolean('send_to_all')->default(false);
            $table->boolean('marketing_only')->default(false);
            $table->json('filters')->nullable();
            $table->json('recipient_emails')->nullable();
            $table->string('subject_override')->nullable();
            $table->longText('body_override')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('communication_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_campaign_id')->constrained('communication_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status', 24)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['communication_campaign_id', 'status'], 'campaign_recipient_status_idx');
        });

        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('communication_campaign_id')->nullable()->constrained('communication_campaigns')->nullOnDelete();
            $table->foreignId('communication_recipient_id')->nullable()->constrained('communication_recipients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 24)->default('email');
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->string('template_name')->nullable();
            $table->string('status', 24)->default('pending');
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['recipient', 'status'], 'communication_logs_recipient_status_idx');
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('type', 32)->default('system');
            $table->string('image_url')->nullable();
            $table->string('action_url')->nullable();
            $table->string('media_type', 32)->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->boolean('is_global')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at'], 'user_notifications_read_idx');
            $table->index(['is_global', 'created_at'], 'user_notifications_global_idx');
        });

        Schema::create('content_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('title');
            $table->string('type', 32)->default('movie');
            $table->text('message')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('admin_notes')->nullable();
            $table->string('requested_from', 24)->default('web');
            $table->boolean('notify_on_status_change')->default(true);
            $table->timestamps();

            $table->index(['status', 'type'], 'content_requests_status_type_idx');
        });

        Schema::create('media_playback_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('playback_session_id')->nullable()->constrained('playback_sessions')->nullOnDelete();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('media_type', 32);
            $table->unsignedBigInteger('media_id');
            $table->unsignedBigInteger('episode_id')->nullable();
            $table->string('error_type', 32)->default('unknown');
            $table->text('error_message')->nullable();
            $table->string('playback_url')->nullable();
            $table->string('device', 24)->default('web');
            $table->string('app_version', 64)->nullable();
            $table->unsignedInteger('load_time_ms')->nullable();
            $table->unsignedInteger('buffering_count')->nullable();
            $table->unsignedInteger('buffering_duration_ms')->nullable();
            $table->unsignedInteger('report_count')->default(1);
            $table->string('status', 24)->default('open');
            $table->boolean('needs_attention')->default(false);
            $table->boolean('is_slow')->default(false);
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['media_type', 'media_id', 'episode_id'], 'playback_reports_media_idx');
            $table->index(['error_type', 'status'], 'playback_reports_error_status_idx');
        });

        Schema::create('admin_alert_settings', function (Blueprint $table) {
            $table->id();
            $table->string('alert_email')->default('smoggrafton@gmail.com');
            $table->boolean('alert_on_registration')->default(true);
            $table->boolean('alert_on_payment_success')->default(true);
            $table->boolean('alert_on_payment_failure')->default(true);
            $table->boolean('alert_on_content_request')->default(true);
            $table->boolean('alert_on_comment')->default(true);
            $table->boolean('alert_on_comment_reply')->default(true);
            $table->boolean('alert_on_playback_issue')->default(true);
            $table->boolean('alert_on_campaign_summary')->default(true);
            $table->unsignedInteger('playback_failure_threshold')->default(3);
            $table->unsignedInteger('slow_start_threshold_ms')->default(8000);
            $table->unsignedInteger('high_failure_rate_threshold')->default(25);
            $table->timestamps();
        });

        Schema::create('admin_activity_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 48);
            $table->string('title');
            $table->text('message');
            $table->json('payload')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status'], 'admin_activity_alerts_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_alerts');
        Schema::dropIfExists('admin_alert_settings');
        Schema::dropIfExists('media_playback_reports');
        Schema::dropIfExists('content_requests');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('communication_logs');
        Schema::dropIfExists('communication_recipients');
        Schema::dropIfExists('communication_campaigns');

        Schema::table('email_templates', function (Blueprint $table) {
            foreach (['preheader', 'preview_text', 'template_type'] as $column) {
                if (Schema::hasColumn('email_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['marketing_emails_enabled', 'marketing_opt_in_token'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
