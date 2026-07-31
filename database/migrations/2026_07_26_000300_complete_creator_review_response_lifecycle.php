<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('creator_applications')) {
            Schema::table('creator_applications', function (Blueprint $table): void {
                if (! Schema::hasColumn('creator_applications', 'resubmitted_at')) {
                    $table->timestamp('resubmitted_at')->nullable()->after('submitted_at');
                }
            });
        }

        if (Schema::hasTable('creator_information_requests')) {
            Schema::table('creator_information_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('creator_information_requests', 'step_key')) {
                    $table->string('step_key', 40)->default('verification')->after('field_type');
                }
            });
        }

        if (Schema::hasTable('creator_information_responses')
            && Schema::hasColumn('creator_information_responses', 'submitted_at')) {
            Schema::table('creator_information_responses', function (Blueprint $table): void {
                $table->timestamp('submitted_at')->nullable()->change();
            });
        }

        if (Schema::hasTable('user_notifications')) {
            Schema::table('user_notifications', function (Blueprint $table): void {
                if (! Schema::hasColumn('user_notifications', 'data')) {
                    $table->json('data')->nullable()->after('action_url');
                }
                $table->string('type', 80)->default('system')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_notifications')) {
            Schema::table('user_notifications', function (Blueprint $table): void {
                if (Schema::hasColumn('user_notifications', 'data')) {
                    $table->dropColumn('data');
                }
                $table->string('type', 32)->default('system')->change();
            });
        }

        if (Schema::hasTable('creator_information_responses')
            && Schema::hasColumn('creator_information_responses', 'submitted_at')) {
            Schema::table('creator_information_responses', function (Blueprint $table): void {
                $table->timestamp('submitted_at')->nullable(false)->change();
            });
        }

        if (Schema::hasTable('creator_information_requests')) {
            Schema::table('creator_information_requests', function (Blueprint $table): void {
                if (Schema::hasColumn('creator_information_requests', 'step_key')) {
                    $table->dropColumn('step_key');
                }
            });
        }

        if (Schema::hasTable('creator_applications')) {
            Schema::table('creator_applications', function (Blueprint $table): void {
                if (Schema::hasColumn('creator_applications', 'resubmitted_at')) {
                    $table->dropColumn('resubmitted_at');
                }
            });
        }
    }
};
