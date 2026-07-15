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
        // Phone verification codes (OTP for phone login)
        if (!Schema::hasTable('phone_verification_codes')) {
            Schema::create('phone_verification_codes', function (Blueprint $table) {
                $table->id();
                $table->string('phone');
                $table->string('code', 6);
                $table->timestamp('expires_at');
                $table->boolean('used')->default(false);
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamps();

                $table->index(['phone', 'code']);
                $table->index('expires_at');
            });
        }

        // Social accounts (Google/Apple and future providers)
        if (!Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('provider'); // e.g. google, apple
                $table->string('provider_user_id');
                $table->string('email')->nullable();
                $table->json('raw_profile')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'provider_user_id']);
                $table->index(['user_id', 'provider']);
            });
        }

        // Push devices (registered app/browser devices)
        if (!Schema::hasTable('push_devices')) {
            Schema::create('push_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->string('platform')->default('unknown'); // android, ios, web, other
                $table->string('provider')->default('fcm'); // fcm, onesignal, custom
                $table->string('token');
                $table->string('device_id')->nullable();
                $table->string('device_name')->nullable();
                $table->string('app_version')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'token']);
                $table->index(['user_id', 'platform']);
            });
        }

        // Push notifications (admin-created or auto-created messages)
        if (!Schema::hasTable('push_notifications')) {
            Schema::create('push_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('body');
                $table->string('image_url')->nullable();
                $table->string('deep_link')->nullable(); // e.g. app route / screen identifier
                $table->string('target_platform')->default('all'); // all, android, ios, web
                $table->string('target_audience')->default('all'); // all, subscribed, free, custom
                $table->json('filters')->nullable(); // JSON blob for future audience rules
                $table->string('provider')->default('default'); // fcm, onesignal, etc.
                $table->enum('status', ['draft', 'queued', 'sending', 'sent', 'failed'])->default('draft');
                $table->timestamp('sent_at')->nullable();
                $table->unsignedInteger('success_count')->default(0);
                $table->unsignedInteger('failure_count')->default(0);
                $table->json('last_error')->nullable();
                $table->timestamps();
            });
        }

        // Ad banners (image/script banners for web/app)
        if (!Schema::hasTable('ad_banners')) {
            Schema::create('ad_banners', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->enum('type', ['image', 'script'])->default('image');
                $table->string('image_path')->nullable();
                $table->text('script_content')->nullable();
                $table->string('target_url')->nullable();
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('placement')->default('global'); // e.g. home_hero, home_sidebar, player_overlay
                $table->string('platform')->default('all'); // app, web, all
                $table->boolean('is_active')->default(true);
                $table->timestamp('active_from')->nullable();
                $table->timestamp('active_until')->nullable();
                $table->integer('sort_order')->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['placement', 'platform', 'is_active']);
                $table->index(['active_from', 'active_until']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop in reverse order, guarding in case tables were removed/renamed.
        if (Schema::hasTable('ad_banners')) {
            Schema::dropIfExists('ad_banners');
        }

        if (Schema::hasTable('push_notifications')) {
            Schema::dropIfExists('push_notifications');
        }

        if (Schema::hasTable('push_devices')) {
            Schema::dropIfExists('push_devices');
        }

        if (Schema::hasTable('social_accounts')) {
            Schema::dropIfExists('social_accounts');
        }

        if (Schema::hasTable('phone_verification_codes')) {
            Schema::dropIfExists('phone_verification_codes');
        }
    }
};

