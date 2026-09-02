<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('brand_id')->index();
            // Unique across brands, not per brand: the public URL names the
            // handle and nothing else, so two brands sharing one would answer
            // the same address with two different questionnaires.
            $table->string('handle', 100)->unique();
            $table->string('title', 191);
            $table->text('intro')->nullable();
            $table->text('outro')->nullable();
            $table->boolean('published')->default(false);
            $table->json('collect')->nullable();
            $table->json('scoring')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->text('text');
            $table->text('help')->nullable();
            $table->string('type', 20)->default('single');
            $table->json('options')->nullable();
            $table->integer('min')->nullable();
            $table->integer('max')->nullable();
            $table->integer('points_per_step')->nullable();
            $table->timestamps();

            $table->index(['assessment_id', 'position']);
        });

        Schema::create('assessment_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->unsignedBigInteger('brand_id')->index();
            $table->string('email', 191)->index();
            $table->string('name', 191)->nullable();
            $table->json('answers');
            $table->integer('score')->default(0);
            $table->string('result_key', 100)->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('visit_token', 64)->unique();
            $table->timestamp('created_at')->nullable();

            $table->index(['assessment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_responses');
        Schema::dropIfExists('assessment_questions');
        Schema::dropIfExists('assessments');
    }
};
