<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->makeDraftMovieFieldsNullable();
        $this->makeDraftShowFieldsNullable();
        $this->dropGlobalTvShowTmdbUniqueness();

        Schema::table('movies', function (Blueprint $table): void {
            $this->addSharedContentFields($table, 'moderation_notes');
        });

        Schema::table('tv_shows', function (Blueprint $table): void {
            $this->addSharedContentFields($table, 'moderation_notes');
        });

        Schema::table('episodes', function (Blueprint $table): void {
            if (! Schema::hasColumn('episodes', 'release_date')) {
                $table->date('release_date')->nullable()->after('description');
            }
            if (! Schema::hasColumn('episodes', 'is_active')) {
                // Preserve existing published episodes. Creator-created drafts explicitly
                // set this to false until moderation approves them.
                $table->boolean('is_active')->default(true)->after('download_enabled');
            }
            if (! Schema::hasColumn('episodes', 'translation_language')) {
                $table->string('translation_language', 100)->nullable()->after('duration');
            }
            if (! Schema::hasColumn('episodes', 'short_description')) {
                $table->text('short_description')->nullable()->after('description');
            }
            if (! Schema::hasColumn('episodes', 'tags')) {
                $table->json('tags')->nullable()->after('short_description');
            }
        });

        foreach (['vjs', 'media_libraries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'languages')) {
                    $table->json('languages')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'location')) {
                    $table->string('location')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'official_links')) {
                    $table->json('official_links')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'public_email')) {
                    $table->string('public_email')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'profile_review_status')) {
                    $table->string('profile_review_status', 30)->default('approved')->index();
                }
            });
        }

        if (! Schema::hasTable('creator_profile_ownership_history')) {
            Schema::create('creator_profile_ownership_history', function (Blueprint $table): void {
                $table->id();
                $table->morphs('profile');
                $table->foreignId('previous_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('new_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('claim_id')->nullable()->constrained('creator_claims')->nullOnDelete();
                $table->string('action', 40);
                $table->text('reason')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('creator_profile_change_requests')) {
            Schema::create('creator_profile_change_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->morphs('profile');
                $table->json('requested_changes');
                $table->string('status', 30)->default('submitted');
                $table->text('creator_message')->nullable();
                $table->text('review_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasTable('creator_support_requests')) {
            Schema::create('creator_support_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('category', 40);
                $table->string('subject');
                $table->text('message');
                $table->string('status', 30)->default('open');
                $table->uuid('submission_id')->nullable();
                $table->foreignId('movie_id')->nullable()->constrained('movies')->nullOnDelete();
                $table->foreignId('tv_show_id')->nullable()->constrained('tv_shows')->nullOnDelete();
                $table->foreignId('episode_id')->nullable()->constrained('episodes')->nullOnDelete();
                $table->foreignId('withdrawal_id')->nullable()->constrained('creator_withdrawal_requests')->nullOnDelete();
                $table->text('admin_response')->nullable();
                $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index(['category', 'status']);
            });
        }

        $this->migrateLegacyClaimRows();
        $this->addWithdrawalPayoutForeignKeyWhenSafe();
    }

    public function down(): void
    {
        // Intentionally non-destructive. Creator identity history, support records,
        // finance links and submitted metadata must survive a rollback deployment.
    }

    private function addSharedContentFields(Blueprint $table, string $after): void
    {
        $tableName = $table->getTable();
        if (! Schema::hasColumn($tableName, 'short_description')) {
            $table->text('short_description')->nullable()->after($after);
        }
        if (! Schema::hasColumn($tableName, 'director')) {
            $table->string('director')->nullable()->after('short_description');
        }
        if (! Schema::hasColumn($tableName, 'translation_language')) {
            $table->string('translation_language', 100)->nullable()->after('director');
        }
        if (! Schema::hasColumn($tableName, 'trailer_url')) {
            $table->string('trailer_url', 2048)->nullable()->after('translation_language');
        }
        if (! Schema::hasColumn($tableName, 'tags')) {
            $table->json('tags')->nullable()->after('trailer_url');
        }
        if (! Schema::hasColumn($tableName, 'seo_title')) {
            $table->string('seo_title')->nullable()->after('tags');
        }
        if (! Schema::hasColumn($tableName, 'seo_description')) {
            $table->text('seo_description')->nullable()->after('seo_title');
        }
        if (! Schema::hasColumn($tableName, 'ownership_declaration_accepted_at')) {
            $table->timestamp('ownership_declaration_accepted_at')->nullable()->after('seo_description');
        }
    }

    private function makeDraftMovieFieldsNullable(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE movies MODIFY thumbnail VARCHAR(255) NULL');
            DB::statement('ALTER TABLE movies MODIFY backdrop VARCHAR(255) NULL');
            DB::statement('ALTER TABLE movies MODIFY release_date DATE NULL');
        } else {
            Schema::table('movies', function (Blueprint $table): void {
                $table->string('thumbnail')->nullable()->change();
                $table->string('backdrop')->nullable()->change();
                $table->date('release_date')->nullable()->change();
            });
        }
    }

    private function makeDraftShowFieldsNullable(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tv_shows MODIFY thumbnail VARCHAR(255) NULL');
            DB::statement('ALTER TABLE tv_shows MODIFY backdrop VARCHAR(255) NULL');
            DB::statement('ALTER TABLE tv_shows MODIFY release_date DATE NULL');
        } else {
            Schema::table('tv_shows', function (Blueprint $table): void {
                $table->string('thumbnail')->nullable()->change();
                $table->string('backdrop')->nullable()->change();
                $table->date('release_date')->nullable()->change();
            });
        }
    }

    private function dropGlobalTvShowTmdbUniqueness(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $hasGlobalIndex = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'tv_shows')
            ->where('index_name', 'tv_shows_tmdb_id_unique')
            ->exists();
        if ($hasGlobalIndex) {
            DB::statement('ALTER TABLE tv_shows DROP INDEX tv_shows_tmdb_id_unique');
        }

        $hasScopedIndex = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'tv_shows')
            ->where('index_name', 'tv_shows_tmdb_id_vj_id_unique')
            ->exists();
        if (! $hasScopedIndex) {
            DB::statement('ALTER TABLE tv_shows ADD UNIQUE tv_shows_tmdb_id_vj_id_unique (tmdb_id, vj_id)');
        }
    }

    private function migrateLegacyClaimRows(): void
    {
        if (! Schema::hasTable('vj_claim_requests') || ! Schema::hasTable('creator_claims')) {
            return;
        }

        DB::table('vj_claim_requests')->orderBy('id')->each(function (object $legacy): void {
            $applicationId = DB::table('creator_applications')
                ->where('user_id', $legacy->user_id)
                ->value('id');
            if (! $applicationId) {
                return;
            }

            DB::table('creator_claims')->updateOrInsert(
                [
                    'user_id' => $legacy->user_id,
                    'claimable_type' => \App\Models\VJ::class,
                    'claimable_id' => $legacy->vj_id,
                ],
                [
                    'creator_application_id' => $applicationId,
                    'status' => $legacy->status === 'approved' ? 'legacy_approved' : 'legacy_unverified',
                    'creator_name' => DB::table('vjs')->where('id', $legacy->vj_id)->value('name'),
                    'relationship_explanation' => 'Migrated from the legacy VJ claim workflow. Evidence must be reviewed before privileges are granted.',
                    'verification_method' => 'legacy_unverified',
                    'review_notes' => $legacy->rejection_reason,
                    'reviewed_by' => $legacy->reviewed_by,
                    'reviewed_at' => $legacy->reviewed_at,
                    'submitted_at' => $legacy->created_at,
                    'created_at' => $legacy->created_at,
                    'updated_at' => now(),
                ]
            );
        });
    }

    private function addWithdrawalPayoutForeignKeyWhenSafe(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $hasConstraint = DB::table('information_schema.key_column_usage')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'creator_withdrawal_requests')
            ->where('column_name', 'payout_method_id')
            ->whereNotNull('referenced_table_name')
            ->exists();
        $hasOrphans = DB::table('creator_withdrawal_requests as withdrawals')
            ->leftJoin('creator_payout_methods as methods', 'methods.id', '=', 'withdrawals.payout_method_id')
            ->whereNull('methods.id')
            ->exists();

        if (! $hasConstraint && ! $hasOrphans) {
            DB::statement(
                'ALTER TABLE creator_withdrawal_requests ADD CONSTRAINT creator_withdrawal_requests_payout_method_id_foreign FOREIGN KEY (payout_method_id) REFERENCES creator_payout_methods(id) ON DELETE RESTRICT'
            );
        }
    }
};
