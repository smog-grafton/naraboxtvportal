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
        Schema::create('article_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->onDelete('cascade');
            $table->enum('type', ['text', 'image', 'quote', 'gallery']);
            $table->text('value')->nullable(); // For text, image URL, quote text
            $table->string('caption')->nullable(); // For images
            $table->string('author')->nullable(); // For quotes
            $table->json('gallery_images')->nullable(); // For gallery type
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_blocks');
    }
};
