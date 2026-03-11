<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_show_actor', function (Blueprint $table) {
            $table->foreignId('tv_show_id')->constrained('tv_shows')->onDelete('cascade');
            $table->foreignId('actor_id')->constrained('actors')->onDelete('cascade');
            $table->string('role')->nullable();
            $table->integer('order')->default(0);
            $table->primary(['tv_show_id', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_show_actor');
    }
};
