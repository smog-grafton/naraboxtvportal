<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_shows', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('thumbnail');
            $table->string('backdrop');
            $table->decimal('rating', 3, 1)->default(0);
            $table->date('release_date');
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');
            $table->foreignId('vj_id')->nullable()->constrained('vjs')->onDelete('set null');
            $table->string('duration')->nullable();
            $table->integer('trending_score')->default(0);
            $table->enum('access_type', ['FREE', 'PREMIUM', 'RENT', 'BUY'])->default('FREE');
            $table->decimal('price_rent', 10, 2)->nullable();
            $table->decimal('price_buy', 10, 2)->nullable();
            $table->string('certificate')->nullable();
            $table->string('country')->nullable();
            $table->string('original_language')->nullable();
            $table->string('language')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('featured_order')->nullable();
            $table->boolean('download_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_shows');
    }
};
