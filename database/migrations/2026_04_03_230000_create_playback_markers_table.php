<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playback_markers', function (Blueprint $table) {
            $table->id();
            $table->morphs('markerable');
            $table->string('marker_type', 32);
            $table->decimal('start_seconds', 10, 2);
            $table->decimal('end_seconds', 10, 2)->nullable();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['marker_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playback_markers');
    }
};
