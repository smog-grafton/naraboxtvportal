<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('media_libraries')) {
            Schema::create('media_libraries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('image')->nullable();
                $table->string('banner')->nullable();
                $table->text('bio')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_verified')->default(false);
                $table->boolean('is_featured')->default(false);
                $table->integer('featured_order')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_libraries');
    }
};
