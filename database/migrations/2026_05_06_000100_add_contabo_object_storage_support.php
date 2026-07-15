<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE video_sources MODIFY type VARCHAR(50) NOT NULL DEFAULT 'url'");
        DB::statement("ALTER TABLE download_sources MODIFY type VARCHAR(50) NOT NULL DEFAULT 'url'");

        if (! Schema::hasTable('contabo_object_storage_buckets')) {
            Schema::create('contabo_object_storage_buckets', function (Blueprint $table) {
                $table->id();
                $table->string('name')->default('NaraboxTV Contabo Storage');
                $table->string('bucket')->default('nbx');
                $table->string('endpoint')->default('https://usc1.contabostorage.com');
                $table->string('public_url')->nullable();
                $table->string('path_prefix')->nullable();
                $table->string('disk')->default('contabo');
                $table->string('object_storage_id')->nullable();
                $table->string('s3_tenant_id')->nullable();
                $table->string('user_id')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        DB::table('video_sources')->where('type', 'contabo_object_storage')->update(['type' => 'url']);
        DB::table('download_sources')->where('type', 'contabo_object_storage')->update(['type' => 'url']);

        Schema::dropIfExists('contabo_object_storage_buckets');
    }
};
