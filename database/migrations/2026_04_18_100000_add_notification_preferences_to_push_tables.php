<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_devices')) {
            Schema::table('push_devices', function (Blueprint $table) {
                if (!Schema::hasColumn('push_devices', 'notifications_enabled')) {
                    $table->boolean('notifications_enabled')->default(true)->after('is_active');
                }

                if (!Schema::hasColumn('push_devices', 'marketing_opt_in')) {
                    $table->boolean('marketing_opt_in')->default(false)->after('notifications_enabled');
                }

                if (!Schema::hasColumn('push_devices', 'tags')) {
                    $table->json('tags')->nullable()->after('marketing_opt_in');
                }
            });
        }

        if (Schema::hasTable('push_notifications')) {
            Schema::table('push_notifications', function (Blueprint $table) {
                if (!Schema::hasColumn('push_notifications', 'notification_type')) {
                    $table->enum('notification_type', ['transactional', 'marketing'])
                        ->default('transactional')
                        ->after('provider');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('push_notifications')) {
            Schema::table('push_notifications', function (Blueprint $table) {
                foreach (['notification_type'] as $column) {
                    if (Schema::hasColumn('push_notifications', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('push_devices')) {
            Schema::table('push_devices', function (Blueprint $table) {
                foreach (['notifications_enabled', 'marketing_opt_in', 'tags'] as $column) {
                    if (Schema::hasColumn('push_devices', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
