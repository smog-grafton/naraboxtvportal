<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_actor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('movies')->onDelete('cascade');
            $table->foreignId('actor_id')->constrained('actors')->onDelete('cascade');
            $table->string('role')->nullable(); // Character name
            $table->integer('order')->default(0);
            $table->timestamps();
            
            $table->unique(['media_id', 'actor_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_actor');
    }
};
