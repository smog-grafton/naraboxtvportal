<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['movies', 'tv_shows'] as $table) {
            if (! Schema::hasTable($table)
                || ! Schema::hasColumns($table, [
                    'submission_origin',
                    'content_status',
                    'publication_status',
                    'editorial_status',
                    'publish_status',
                    'published_at',
                ])) {
                continue;
            }

            DB::table($table)
                ->where('submission_origin', 'administrator')
                ->where('content_status', 'published')
                ->update([
                    'publication_status' => 'published',
                    'editorial_status' => 'approved',
                    'publish_status' => 'published',
                    'published_at' => DB::raw('COALESCE(published_at, updated_at, created_at)'),
                ]);
        }
    }

    public function down(): void
    {
        // Deliberately irreversible: published administrator records existed
        // before the creator lifecycle columns and must not become drafts again.
    }
};
