<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('articles')) {
            return;
        }

        DB::statement('ALTER TABLE articles MODIFY category VARCHAR(255) NULL');
        DB::statement('ALTER TABLE articles MODIFY image VARCHAR(255) NULL');

        Schema::table('articles', function (Blueprint $table) {
            if (! Schema::hasColumn('articles', 'post_type')) {
                $table->string('post_type')->default('news')->after('slug')->index();
            }

            if (! Schema::hasColumn('articles', 'author_user_id')) {
                $table->foreignId('author_user_id')->nullable()->after('author')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('articles', 'primary_category_id')) {
                $table->foreignId('primary_category_id')->nullable()->after('category')->constrained('editorial_categories')->nullOnDelete();
            }

            if (! Schema::hasColumn('articles', 'movie_id')) {
                $table->foreignId('movie_id')->nullable()->after('primary_category_id')->constrained('movies')->nullOnDelete();
            }

            if (! Schema::hasColumn('articles', 'tv_show_id')) {
                $table->foreignId('tv_show_id')->nullable()->after('movie_id')->constrained('tv_shows')->nullOnDelete();
            }

            if (! Schema::hasColumn('articles', 'vj_id')) {
                $table->foreignId('vj_id')->nullable()->after('tv_show_id')->constrained('vjs')->nullOnDelete();
            }

            if (! Schema::hasColumn('articles', 'review_target_type')) {
                $table->string('review_target_type')->nullable()->after('vj_id');
            }

            if (! Schema::hasColumn('articles', 'review_target_id')) {
                $table->unsignedBigInteger('review_target_id')->nullable()->after('review_target_type');
            }

            if (! Schema::hasColumn('articles', 'score')) {
                $table->decimal('score', 3, 1)->nullable()->after('review_target_id');
            }

            if (! Schema::hasColumn('articles', 'verdict')) {
                $table->text('verdict')->nullable()->after('score');
            }

            if (! Schema::hasColumn('articles', 'pros')) {
                $table->json('pros')->nullable()->after('verdict');
            }

            if (! Schema::hasColumn('articles', 'cons')) {
                $table->json('cons')->nullable()->after('pros');
            }

            if (! Schema::hasColumn('articles', 'seo_title')) {
                $table->string('seo_title')->nullable()->after('cons');
            }

            if (! Schema::hasColumn('articles', 'seo_description')) {
                $table->text('seo_description')->nullable()->after('seo_title');
            }

            if (! Schema::hasColumn('articles', 'og_image')) {
                $table->string('og_image')->nullable()->after('seo_description');
            }
        });

        $colorMap = [
            'updates' => 'emerald',
            'movies' => 'amber',
            'tv-shows' => 'sky',
            'industry' => 'rose',
            'platform' => 'zinc',
        ];

        $categories = DB::table('articles')
            ->select('category')
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        foreach ($categories as $name) {
            $slug = Str::slug((string) $name);
            $existingId = DB::table('editorial_categories')->where('slug', $slug)->value('id');

            if (! $existingId) {
                $existingId = DB::table('editorial_categories')->insertGetId([
                    'name' => $name,
                    'slug' => $slug,
                    'description' => null,
                    'color' => $colorMap[$slug] ?? 'emerald',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('articles')
                ->where('category', $name)
                ->whereNull('primary_category_id')
                ->update([
                    'primary_category_id' => $existingId,
                    'post_type' => DB::raw("COALESCE(post_type, 'news')"),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('articles')) {
            return;
        }

        Schema::table('articles', function (Blueprint $table) {
            foreach ([
                'author_user_id',
                'primary_category_id',
                'movie_id',
                'tv_show_id',
                'vj_id',
            ] as $foreignColumn) {
                if (Schema::hasColumn('articles', $foreignColumn)) {
                    $table->dropConstrainedForeignId($foreignColumn);
                }
            }

            foreach ([
                'post_type',
                'review_target_type',
                'review_target_id',
                'score',
                'verdict',
                'pros',
                'cons',
                'seo_title',
                'seo_description',
                'og_image',
            ] as $column) {
                if (Schema::hasColumn('articles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
