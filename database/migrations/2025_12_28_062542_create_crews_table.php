<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crews', function (Blueprint $table) {
            $table->id();
            $table->morphs('crewable'); // movie_id/tv_show_id + crewable_type
            $table->integer('tmdb_id')->nullable();
            $table->string('name');
            $table->string('job'); // Director, Writer, Producer, etc.
            $table->string('department'); // Directing, Writing, Production, etc.
            $table->string('profile_image')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crews');
    }
};
