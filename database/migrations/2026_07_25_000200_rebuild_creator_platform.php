<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->widenLegacyStatuses();
        $this->extendApplications();
        $this->extendContent();
        $this->extendFinance();
        $this->createIdentityTables();
        $this->createContentWorkflowTables();
        $this->createWalletTables();
        $this->createAuditTable();
    }

    private function widenLegacyStatuses(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            if (Schema::hasTable('creator_applications') && Schema::hasColumn('creator_applications', 'status')) {
                Schema::table('creator_applications', function (Blueprint $table): void {
                    $table->string('status', 40)->default('draft')->change();
                });
            }
            if (Schema::hasTable('vj_claim_requests') && Schema::hasColumn('vj_claim_requests', 'status')) {
                Schema::table('vj_claim_requests', function (Blueprint $table): void {
                    $table->string('status', 40)->default('submitted')->change();
                });
            }
            foreach (['movies', 'tv_shows'] as $tableName) {
                if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'publish_status')) {
                    Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                        $table->string('publish_status', 40)->default('published')->change();
                    });
                }
            }

            return;
        }

        if (Schema::hasTable('creator_applications') && Schema::hasColumn('creator_applications', 'status')) {
            DB::statement("ALTER TABLE creator_applications MODIFY status VARCHAR(40) NOT NULL DEFAULT 'draft'");
        }
        if (Schema::hasTable('vj_claim_requests') && Schema::hasColumn('vj_claim_requests', 'status')) {
            DB::statement("ALTER TABLE vj_claim_requests MODIFY status VARCHAR(40) NOT NULL DEFAULT 'submitted'");
        }
        foreach (['movies', 'tv_shows'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'publish_status')) {
                DB::statement("ALTER TABLE {$table} MODIFY publish_status VARCHAR(40) NOT NULL DEFAULT 'published'");
            }
        }
    }

    private function extendApplications(): void
    {
        Schema::table('creator_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('creator_applications', 'legal_name')) {
                $table->string('legal_name')->nullable();
            }
            if (! Schema::hasColumn('creator_applications', 'phone_number')) {
                $table->string('phone_number', 40)->nullable();
            }
            if (! Schema::hasColumn('creator_applications', 'public_email')) {
                $table->string('public_email')->nullable();
            }
            if (! Schema::hasColumn('creator_applications', 'website_url')) {
                $table->string('website_url', 2048)->nullable();
            }
            if (! Schema::hasColumn('creator_applications', 'social_links')) {
                $table->json('social_links')->nullable();
            }
            if (! Schema::hasColumn('creator_applications', 'application_mode')) {
                $table->string('application_mode', 20)->default('create');
            }
            if (! Schema::hasColumn('creator_applications', 'claimable_type')) {
                $table->string('claimable_type')->nullable();
                $table->unsignedBigInteger('claimable_id')->nullable();
                $table->index(['claimable_type', 'claimable_id'], 'creator_app_claimable_idx');
            }
            if (! Schema::hasColumn('creator_applications', 'verification_status')) {
                $table->string('verification_status', 40)->default('not_submitted')->index();
            }
            if (! Schema::hasColumn('creator_applications', 'challenge_phrase')) {
                $table->text('challenge_phrase')->nullable();
            }
            if (! Schema::hasColumn('creator_applications', 'challenge_code_hash')) {
                $table->string('challenge_code_hash')->nullable();
            }
            if (! Schema::hasColumn('creator_applications', 'challenge_expires_at')) {
                $table->timestamp('challenge_expires_at')->nullable();
            }
            if (! Schema::hasColumn('creator_applications', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }
            if (! Schema::hasColumn('creator_applications', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (! Schema::hasColumn('creator_applications', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable();
                $table->text('suspension_reason')->nullable();
            }
            if (! Schema::hasColumn('creator_applications', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable();
            }
        });
    }

    private function extendContent(): void
    {
        foreach (['movies', 'tv_shows', 'episodes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'submitted_by')) {
                    $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'creator_application_id')) {
                    $table->foreignId('creator_application_id')->nullable()->constrained('creator_applications')->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'submission_origin')) {
                    $table->string('submission_origin', 30)->default('administrator');
                }
                if (! Schema::hasColumn($tableName, 'processing_status')) {
                    $table->string('processing_status', 40)->default('not_started')->index();
                }
                if (! Schema::hasColumn($tableName, 'editorial_status')) {
                    $table->string('editorial_status', 40)->default('not_submitted')->index();
                }
                if (! Schema::hasColumn($tableName, 'publication_status')) {
                    $table->string('publication_status', 40)->default('draft')->index();
                }
                if (! Schema::hasColumn($tableName, 'monetization_status')) {
                    $table->string('monetization_status', 40)->default('disabled')->index();
                }
                if (! Schema::hasColumn($tableName, 'submitted_for_review_at')) {
                    $table->timestamp('submitted_for_review_at')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'approved_by')) {
                    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'published_by')) {
                    $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn($tableName, 'published_at')) {
                    $table->timestamp('published_at')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'scheduled_for')) {
                    $table->timestamp('scheduled_for')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'moderation_notes')) {
                    $table->text('moderation_notes')->nullable();
                }
            });
        }
    }

    private function extendFinance(): void
    {
        Schema::table('creator_payout_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('creator_payout_methods', 'protected_details')) {
                $table->text('protected_details')->nullable();
            }
            if (! Schema::hasColumn('creator_payout_methods', 'details_fingerprint')) {
                $table->string('details_fingerprint', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('creator_payout_methods', 'verification_status')) {
                $table->string('verification_status', 30)->default('pending')->index();
            }
            if (! Schema::hasColumn('creator_payout_methods', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
            }
            if (! Schema::hasColumn('creator_payout_methods', 'changed_at')) {
                $table->timestamp('changed_at')->nullable();
            }
            if (! Schema::hasColumn('creator_payout_methods', 'withdrawal_hold_until')) {
                $table->timestamp('withdrawal_hold_until')->nullable();
            }
        });

        Schema::table('creator_earnings', function (Blueprint $table) {
            if (! Schema::hasColumn('creator_earnings', 'gross_amount_minor')) {
                $table->bigInteger('gross_amount_minor')->nullable();
                $table->bigInteger('net_amount_minor')->nullable();
                $table->bigInteger('creator_amount_minor')->nullable();
                $table->bigInteger('platform_amount_minor')->nullable();
            }
            if (! Schema::hasColumn('creator_earnings', 'creator_share_bps')) {
                $table->unsignedSmallInteger('creator_share_bps')->nullable();
                $table->unsignedSmallInteger('platform_share_bps')->nullable();
            }
            if (! Schema::hasColumn('creator_earnings', 'eligibility_status')) {
                $table->string('eligibility_status', 40)->default('creator_eligible')->index();
            }
            if (! Schema::hasColumn('creator_earnings', 'calculation_snapshot')) {
                $table->json('calculation_snapshot')->nullable();
            }
            if (! Schema::hasColumn('creator_earnings', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique();
            }
        });

        Schema::table('creator_withdrawal_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('creator_withdrawal_requests', 'amount_minor')) {
                $table->bigInteger('amount_minor')->nullable();
            }
            if (! Schema::hasColumn('creator_withdrawal_requests', 'currency')) {
                $table->char('currency', 3)->default('UGX');
            }
            if (! Schema::hasColumn('creator_withdrawal_requests', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique();
            }
            if (! Schema::hasColumn('creator_withdrawal_requests', 'provider_status')) {
                $table->string('provider_status', 50)->nullable()->index();
            }
            if (! Schema::hasColumn('creator_withdrawal_requests', 'last_reconciled_at')) {
                $table->timestamp('last_reconciled_at')->nullable();
                $table->timestamp('provider_confirmed_at')->nullable();
            }
        });

        Schema::table('financial_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('financial_settings', 'creator_share_bps')) {
                $table->unsignedSmallInteger('creator_share_bps')->default(7000);
                $table->unsignedSmallInteger('platform_share_bps')->default(3000);
                $table->unsignedSmallInteger('subscription_pool_bps')->default(4000);
            }
            if (! Schema::hasColumn('financial_settings', 'min_withdrawal_minor')) {
                $table->bigInteger('min_withdrawal_minor')->default(10000);
                $table->bigInteger('max_withdrawal_minor')->nullable();
                $table->bigInteger('withdrawal_fee_minor')->default(0);
                $table->bigInteger('daily_withdrawal_limit_minor')->nullable();
                $table->bigInteger('monthly_withdrawal_limit_minor')->nullable();
            }
            if (! Schema::hasColumn('financial_settings', 'manual_approval_required')) {
                $table->boolean('manual_approval_required')->default(true);
            }
            if (! Schema::hasColumn('financial_settings', 'allowed_payout_methods')) {
                $table->json('allowed_payout_methods')->nullable();
            }
            if (! Schema::hasColumn('financial_settings', 'qualified_watch_seconds')) {
                $table->unsignedInteger('qualified_watch_seconds')->default(300);
                $table->unsignedInteger('max_qualified_plays_per_day')->default(3);
            }
            if (! Schema::hasColumn('financial_settings', 'creator_upload_settings')) {
                $table->json('creator_upload_settings')->nullable();
            }
        });
    }

    private function createIdentityTables(): void
    {
        if (! Schema::hasTable('creator_claims')) {
            Schema::create('creator_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('creator_application_id')->nullable()->constrained('creator_applications')->nullOnDelete();
                $table->string('claimable_type');
                $table->unsignedBigInteger('claimable_id');
                $table->string('status', 40)->default('draft');
                $table->string('legal_name')->nullable();
                $table->string('creator_name');
                $table->string('phone_number', 40)->nullable();
                $table->string('email')->nullable();
                $table->string('official_social_url', 2048)->nullable();
                $table->string('telegram_url', 2048)->nullable();
                $table->string('youtube_url', 2048)->nullable();
                $table->string('facebook_url', 2048)->nullable();
                $table->string('tiktok_url', 2048)->nullable();
                $table->text('existing_business_contact')->nullable();
                $table->string('sample_work_url', 2048)->nullable();
                $table->text('relationship_explanation');
                $table->string('verification_method', 40)->default('private_video');
                $table->text('challenge_phrase')->nullable();
                $table->string('challenge_code_hash')->nullable();
                $table->timestamp('challenge_expires_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['claimable_type', 'claimable_id', 'status'], 'creator_claim_target_idx');
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('creator_verification_evidence')) {
            Schema::create('creator_verification_evidence', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('creator_application_id')->nullable()->constrained('creator_applications')->cascadeOnDelete();
                $table->foreignId('creator_claim_id')->nullable()->constrained('creator_claims')->cascadeOnDelete();
                $table->string('kind', 40);
                $table->string('disk', 40)->default('private');
                $table->text('path');
                $table->string('original_filename');
                $table->string('mime_type', 150);
                $table->unsignedBigInteger('size_bytes');
                $table->string('status', 30)->default('submitted');
                $table->json('metadata')->nullable();
                $table->timestamp('uploaded_at');
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('retention_until')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();
                $table->index(['creator_application_id', 'status'], 'creator_evidence_app_idx');
                $table->index(['creator_claim_id', 'status'], 'creator_evidence_claim_idx');
            });
        }

        if (! Schema::hasTable('creator_information_requests')) {
            Schema::create('creator_information_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('creator_application_id')->nullable()->constrained('creator_applications')->cascadeOnDelete();
                $table->foreignId('creator_claim_id')->nullable()->constrained('creator_claims')->cascadeOnDelete();
                $table->string('field_label');
                $table->text('instructions')->nullable();
                $table->string('field_type', 30);
                $table->boolean('is_required')->default(true);
                $table->json('options')->nullable();
                $table->json('allowed_mime_types')->nullable();
                $table->unsignedInteger('max_file_size_kb')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->text('internal_reason')->nullable();
                $table->text('creator_message')->nullable();
                $table->string('status', 30)->default('open');
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('creator_information_responses')) {
            Schema::create('creator_information_responses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('information_request_id')->constrained('creator_information_requests')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->longText('response_text')->nullable();
                $table->foreignId('evidence_id')->nullable()->constrained('creator_verification_evidence')->nullOnDelete();
                $table->string('status', 30)->default('submitted');
                $table->text('review_feedback')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at');
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['information_request_id', 'status'], 'creator_info_response_idx');
            });
        }

        if (! Schema::hasTable('creator_permissions')) {
            Schema::create('creator_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('creator_application_id')->nullable()->constrained('creator_applications')->nullOnDelete();
                $table->boolean('identity_verified')->default(false);
                $table->boolean('draft_submission_enabled')->default(true);
                $table->boolean('publishing_enabled')->default(false);
                $table->boolean('monetization_enabled')->default(false);
                $table->boolean('withdrawals_enabled')->default(false);
                $table->boolean('profile_editing_enabled')->default(false);
                $table->boolean('is_suspended')->default(false);
                $table->boolean('is_revoked')->default(false);
                $table->text('restriction_reason')->nullable();
                $table->unsignedSmallInteger('creator_share_bps_override')->nullable();
                $table->timestamp('withdrawal_hold_until')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('identity_verified_at')->nullable();
                $table->timestamp('publishing_enabled_at')->nullable();
                $table->timestamp('monetization_enabled_at')->nullable();
                $table->timestamp('withdrawals_enabled_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }
    }

    private function createContentWorkflowTables(): void
    {
        if (! Schema::hasTable('creator_content_submissions')) {
            Schema::create('creator_content_submissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('creator_application_id')->nullable()->constrained('creator_applications')->nullOnDelete();
                $table->string('content_type');
                $table->unsignedBigInteger('content_id');
                $table->string('submission_source', 30)->default('direct_upload');
                $table->string('processing_status', 40)->default('not_started');
                $table->string('editorial_status', 40)->default('not_submitted');
                $table->string('publication_status', 40)->default('draft');
                $table->string('monetization_status', 40)->default('disabled');
                $table->uuid('cdn_asset_id')->nullable()->index();
                $table->unsignedBigInteger('video_source_id')->nullable()->index();
                $table->string('processing_job_id')->nullable()->index();
                $table->unsignedTinyInteger('progress_percent')->nullable();
                $table->string('processing_stage', 80)->nullable();
                $table->text('status_message')->nullable();
                $table->text('failure_reason')->nullable();
                $table->json('processing_profile')->nullable();
                $table->json('result_metadata')->nullable();
                $table->unsignedSmallInteger('retry_count')->default(0);
                $table->timestamp('last_retried_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['content_type', 'content_id'], 'creator_submission_content_idx');
                $table->index(['user_id', 'processing_status'], 'creator_submission_user_idx');
            });
        }

        if (! Schema::hasTable('creator_upload_sessions')) {
            Schema::create('creator_upload_sessions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('submission_id')->constrained('creator_content_submissions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('video_source_id')->nullable()->index();
                $table->string('token_hash', 64)->unique();
                $table->string('filename');
                $table->string('mime_type', 150)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->json('processing_profile')->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->string('remote_ip', 64)->nullable();
                $table->timestamps();
                $table->index(['user_id', 'expires_at']);
            });
        }

        if (! Schema::hasTable('creator_content_ownership_history')) {
            Schema::create('creator_content_ownership_history', function (Blueprint $table) {
                $table->id();
                $table->string('content_type');
                $table->unsignedBigInteger('content_id');
                $table->foreignId('submitting_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('owner_type')->nullable();
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->string('previous_owner_type')->nullable();
                $table->unsignedBigInteger('previous_owner_id')->nullable();
                $table->string('action', 40);
                $table->text('reason')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();
                $table->index(['content_type', 'content_id'], 'creator_owner_content_idx');
                $table->index(['owner_type', 'owner_id'], 'creator_owner_target_idx');
            });
        }

        if (! Schema::hasTable('creator_content_reviews')) {
            Schema::create('creator_content_reviews', function (Blueprint $table) {
                $table->id();
                $table->string('content_type');
                $table->unsignedBigInteger('content_id');
                $table->string('status', 40);
                $table->text('creator_message')->nullable();
                $table->text('internal_notes')->nullable();
                $table->json('requested_changes')->nullable();
                $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('reviewed_at');
                $table->timestamps();
                $table->index(['content_type', 'content_id'], 'creator_review_content_idx');
            });
        }

        if (! Schema::hasTable('creator_monetization_settings')) {
            Schema::create('creator_monetization_settings', function (Blueprint $table) {
                $table->id();
                $table->string('content_type');
                $table->unsignedBigInteger('content_id');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('subscription_enabled')->default(false);
                $table->boolean('rent_enabled')->default(false);
                $table->boolean('purchase_enabled')->default(false);
                $table->bigInteger('rent_price_minor')->nullable();
                $table->bigInteger('purchase_price_minor')->nullable();
                $table->unsignedInteger('rental_duration_hours')->nullable();
                $table->json('subscription_plan_ids')->nullable();
                $table->unsignedSmallInteger('creator_share_bps_override')->nullable();
                $table->string('currency', 3)->default('UGX');
                $table->string('status', 30)->default('draft');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->unique(['content_type', 'content_id'], 'creator_monetization_content_unique');
            });
        }
    }

    private function createWalletTables(): void
    {
        if (! Schema::hasTable('creator_wallets')) {
            Schema::create('creator_wallets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->char('currency', 3)->default('UGX');
                $table->string('status', 30)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('creator_ledger_entries')) {
            Schema::create('creator_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wallet_id')->constrained('creator_wallets')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->bigInteger('amount_minor');
                $table->char('currency', 3)->default('UGX');
                $table->string('entry_type', 50);
                $table->string('bucket', 30);
                $table->string('status', 30)->default('posted');
                $table->string('reference_type')->nullable();
                $table->string('reference_id')->nullable();
                $table->string('idempotency_key')->unique();
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('available_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['wallet_id', 'bucket', 'status'], 'creator_ledger_balance_idx');
                $table->index(['reference_type', 'reference_id'], 'creator_ledger_reference_idx');
            });
        }

        if (! Schema::hasTable('creator_settlements')) {
            Schema::create('creator_settlements', function (Blueprint $table) {
                $table->id();
                $table->string('period', 7)->unique();
                $table->bigInteger('eligible_revenue_minor')->default(0);
                $table->bigInteger('deductions_minor')->default(0);
                $table->unsignedSmallInteger('creator_pool_bps');
                $table->bigInteger('creator_pool_minor')->default(0);
                $table->string('metric', 40)->default('qualified_watch_seconds');
                $table->unsignedBigInteger('total_qualified_metric')->default(0);
                $table->string('status', 30)->default('draft');
                $table->json('calculation_snapshot')->nullable();
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finalized_at')->nullable();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('creator_settlement_allocations')) {
            Schema::create('creator_settlement_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('settlement_id')->constrained('creator_settlements')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('wallet_id')->nullable()->constrained('creator_wallets')->nullOnDelete();
                $table->string('content_type')->nullable();
                $table->unsignedBigInteger('content_id')->nullable();
                $table->unsignedBigInteger('qualified_metric')->default(0);
                $table->bigInteger('amount_minor')->default(0);
                $table->string('eligibility_status', 40);
                $table->string('idempotency_key')->unique();
                $table->json('calculation_snapshot')->nullable();
                $table->timestamps();
                $table->index(['settlement_id', 'user_id']);
            });
        }
    }

    private function createAuditTable(): void
    {
        if (! Schema::hasTable('creator_audit_logs')) {
            Schema::create('creator_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event', 100);
                $table->string('auditable_type')->nullable();
                $table->string('auditable_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['auditable_type', 'auditable_id'], 'creator_audit_subject_idx');
                $table->index(['subject_user_id', 'event']);
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. Creator identity evidence, ownership history,
        // settlement snapshots and financial ledgers must never be silently discarded.
    }
};
