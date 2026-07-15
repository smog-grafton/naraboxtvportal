<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('article_blocks')) {
            return;
        }

        DB::statement("ALTER TABLE article_blocks MODIFY type ENUM('text', 'rich_text', 'image', 'quote', 'gallery', 'movie_embed', 'tv_show_embed', 'vj_embed', 'cta') NOT NULL");

        Schema::table('article_blocks', function (Blueprint $table) {
            if (! Schema::hasColumn('article_blocks', 'author_title')) {
                $table->string('author_title')->nullable()->after('author');
            }

            if (! Schema::hasColumn('article_blocks', 'alt_text')) {
                $table->string('alt_text')->nullable()->after('caption');
            }

            if (! Schema::hasColumn('article_blocks', 'movie_id')) {
                $table->foreignId('movie_id')->nullable()->after('gallery_images')->constrained('movies')->nullOnDelete();
            }

            if (! Schema::hasColumn('article_blocks', 'tv_show_id')) {
                $table->foreignId('tv_show_id')->nullable()->after('movie_id')->constrained('tv_shows')->nullOnDelete();
            }

            if (! Schema::hasColumn('article_blocks', 'vj_id')) {
                $table->foreignId('vj_id')->nullable()->after('tv_show_id')->constrained('vjs')->nullOnDelete();
            }

            if (! Schema::hasColumn('article_blocks', 'cta_label')) {
                $table->string('cta_label')->nullable()->after('vj_id');
            }

            if (! Schema::hasColumn('article_blocks', 'cta_url')) {
                $table->string('cta_url')->nullable()->after('cta_label');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('article_blocks')) {
            return;
        }

        Schema::table('article_blocks', function (Blueprint $table) {
            foreach (['movie_id', 'tv_show_id', 'vj_id'] as $foreignColumn) {
                if (Schema::hasColumn('article_blocks', $foreignColumn)) {
                    $table->dropConstrainedForeignId($foreignColumn);
                }
            }

            foreach (['author_title', 'alt_text', 'cta_label', 'cta_url'] as $column) {
                if (Schema::hasColumn('article_blocks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
