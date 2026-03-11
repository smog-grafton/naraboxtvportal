<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('telegram_imports')) {
            return;
        }

        Schema::create('telegram_imports', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_chat_id', 64)->nullable();
            $table->string('telegram_message_id', 64)->nullable();
            $table->string('telegram_channel', 255)->nullable();
            $table->string('title_guess', 255)->nullable();
            $table->string('vj_guess', 255)->nullable();
            $table->string('episode_guess', 50)->nullable();
            $table->uuid('cdn_asset_id')->nullable();
            $table->unsignedBigInteger('cdn_source_id')->nullable();
            $table->string('status', 32)->default('uploaded');
            $table->json('raw_metadata')->nullable();
            $table->timestamps();

            $table->unique(['telegram_chat_id', 'telegram_message_id'], 'telegram_imports_chat_message_unique');
            $table->index('status');
            $table->index('cdn_asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_imports');
    }
};
