<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_sources', function (Blueprint $table): void {
            $table->string('media_role', 48)->nullable()->after('format')->index();
            $table->string('server_key', 64)->nullable()->after('media_role')->index();
            $table->string('source_group', 191)->nullable()->after('server_key')->index();
            $table->string('quality_label', 32)->nullable()->after('source_group');
            $table->string('health_status', 24)->default('unknown')->after('is_active')->index();
            $table->timestamp('last_health_check_at')->nullable()->after('health_status');
            $table->unsignedSmallInteger('last_http_status')->nullable()->after('last_health_check_at');
            $table->text('last_health_error')->nullable()->after('last_http_status');
            $table->unsignedSmallInteger('consecutive_failures')->default(0)->after('last_health_error');
            $table->timestamp('verified_at')->nullable()->after('consecutive_failures');
            $table->string('storage_disk', 64)->nullable()->after('verified_at');
            $table->string('storage_bucket', 191)->nullable()->after('storage_disk');
            $table->text('storage_object_key')->nullable()->after('storage_bucket');
            $table->uuid('nbx_asset_id')->nullable()->after('storage_object_key')->index();
            $table->string('processing_job_id', 191)->nullable()->after('nbx_asset_id')->index();
            $table->timestamp('deleted_from_storage_at')->nullable()->after('processing_job_id');
            $table->timestamp('primary_changed_at')->nullable()->after('deleted_from_storage_at');

            $table->index(
                ['sourceable_type', 'sourceable_id', 'is_active', 'health_status'],
                'video_sources_owner_health_index'
            );
        });

        Schema::table('media_playback_reports', function (Blueprint $table): void {
            $table->string('source_role', 48)->nullable()->after('source_id');
            $table->string('server_key', 64)->nullable()->after('source_role');
            $table->string('source_format', 24)->nullable()->after('server_key');
            $table->unsignedSmallInteger('http_status')->nullable()->after('error_message');
            $table->string('player_error_code', 96)->nullable()->after('http_status');
            $table->string('platform', 32)->nullable()->after('device');
            $table->unsignedTinyInteger('attempt_number')->nullable()->after('platform');
            $table->unsignedBigInteger('fallback_source_id')->nullable()->after('attempt_number');
            $table->boolean('fallback_succeeded')->nullable()->after('fallback_source_id');
            $table->unsignedInteger('startup_time_ms')->nullable()->after('fallback_succeeded');

            $table->index(['source_id', 'created_at'], 'playback_reports_source_created_index');
            $table->index(['fallback_source_id', 'fallback_succeeded'], 'playback_reports_fallback_index');
        });
    }

    public function down(): void
    {
        Schema::table('media_playback_reports', function (Blueprint $table): void {
            $table->dropIndex('playback_reports_source_created_index');
            $table->dropIndex('playback_reports_fallback_index');
            $table->dropColumn([
                'source_role',
                'server_key',
                'source_format',
                'http_status',
                'player_error_code',
                'platform',
                'attempt_number',
                'fallback_source_id',
                'fallback_succeeded',
                'startup_time_ms',
            ]);
        });

        Schema::table('video_sources', function (Blueprint $table): void {
            $table->dropIndex('video_sources_owner_health_index');
            $table->dropColumn([
                'media_role',
                'server_key',
                'source_group',
                'quality_label',
                'health_status',
                'last_health_check_at',
                'last_http_status',
                'last_health_error',
                'consecutive_failures',
                'verified_at',
                'storage_disk',
                'storage_bucket',
                'storage_object_key',
                'nbx_asset_id',
                'processing_job_id',
                'deleted_from_storage_at',
                'primary_changed_at',
            ]);
        });
    }
};
