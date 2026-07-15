<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_visitors', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_key', 191)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 16);
            $table->string('guest_id', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('last_path', 255)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['platform', 'last_seen_at']);
            $table->index(['user_id', 'platform']);
            $table->index('guest_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_visitors');
    }
};
