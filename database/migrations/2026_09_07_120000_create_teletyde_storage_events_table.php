<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teletyde_storage_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('job_id', 64)->index();
            $table->foreignId('video_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 32);
            $table->string('status', 32);
            $table->json('payload');
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teletyde_storage_events');
    }
};
