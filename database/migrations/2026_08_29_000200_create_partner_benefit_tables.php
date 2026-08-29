<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_benefit_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('globally_enabled')->default(false)->index();
            $table->json('default_configuration')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_benefits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->foreignId('benefit_type_id')->constrained('partner_benefit_types')->restrictOnDelete();
            $table->boolean('enabled')->default(false)->index();
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('ends_at')->nullable()->index();
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->unique(['partner_id', 'benefit_type_id']);
            $table->index(['partner_id', 'enabled']);
        });

        Schema::create('partner_benefit_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('partner_benefit_id')->constrained('partner_benefits')->restrictOnDelete();
            $table->foreignId('movie_id')->nullable()->constrained('movies')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->unique()->constrained('payment_transactions')->nullOnDelete();
            $table->string('status', 24)->default('reserved')->index();
            $table->dateTime('claimed_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['partner_benefit_id', 'user_id'], 'partner_benefit_user_once');
            $table->index(['partner_id', 'status', 'claimed_at'], 'partner_benefit_claim_reporting');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_benefit_claims');
        Schema::dropIfExists('partner_benefits');
        Schema::dropIfExists('partner_benefit_types');
    }
};
