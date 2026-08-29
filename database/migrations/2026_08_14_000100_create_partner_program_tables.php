<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->string('referral_code', 64)->unique();
            $table->string('profile_image')->nullable();
            $table->text('bio')->nullable();
            $table->json('social_links')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('default_commission_bps')->default(0);
            $table->unsignedInteger('attribution_window_days')->default(30);
            $table->unsignedInteger('commission_duration_days')->default(180);
            $table->unsignedInteger('payout_hold_days')->default(7);
            $table->unsignedBigInteger('minimum_payout_minor')->default(10000);
            $table->json('rate_overrides')->nullable();
            $table->timestamp('agreement_start_at')->nullable()->index();
            $table->timestamp('agreement_end_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('partner_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('referral_code', 64)->unique();
            $table->unsignedSmallInteger('commission_bps')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['partner_id', 'slug']);
            $table->index(['partner_id', 'status']);
        });

        Schema::create('partner_attributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('partner_campaigns')->nullOnDelete();
            $table->string('attribution_method', 32);
            $table->string('referral_code', 64)->nullable()->index();
            $table->dateTime('attributed_at')->index();
            $table->dateTime('commission_valid_from')->index();
            $table->dateTime('commission_valid_until')->nullable()->index();
            $table->dateTime('locked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'commission_valid_until']);
        });

        Schema::create('partner_referral_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('partner_campaigns')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 24)->default('visit')->index();
            $table->string('visitor_key', 128)->nullable()->index();
            $table->string('referral_code', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();

            $table->index(['partner_id', 'campaign_id', 'event_type', 'occurred_at'], 'partner_events_reporting_idx');
        });

        Schema::create('partner_earnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->unique()->constrained('payment_transactions')->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('partner_campaigns')->nullOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('commissionable_amount', 12, 2)->default(0);
            $table->decimal('creator_amount', 12, 2)->default(0);
            $table->decimal('platform_share_before_partner', 12, 2)->default(0);
            $table->decimal('partner_rate', 5, 2)->default(0);
            $table->decimal('partner_amount', 12, 2)->default(0);
            $table->decimal('platform_final_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('gross_amount_minor')->default(0);
            $table->unsignedBigInteger('commissionable_amount_minor')->default(0);
            $table->unsignedBigInteger('creator_amount_minor')->default(0);
            $table->unsignedBigInteger('platform_share_before_partner_minor')->default(0);
            $table->unsignedSmallInteger('partner_rate_bps')->default(0);
            $table->unsignedBigInteger('partner_amount_minor')->default(0);
            $table->unsignedBigInteger('platform_final_amount_minor')->default(0);
            $table->string('status', 24)->default('pending')->index();
            $table->string('idempotency_key')->unique();
            $table->dateTime('earned_at')->index();
            $table->dateTime('available_at')->nullable()->index();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('reversed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
            $table->index(['user_id', 'earned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_earnings');
        Schema::dropIfExists('partner_referral_events');
        Schema::dropIfExists('partner_attributions');
        Schema::dropIfExists('partner_campaigns');
        Schema::dropIfExists('partners');
    }
};
