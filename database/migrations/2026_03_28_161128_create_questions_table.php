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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->text('question_text');
            $table->enum('type', ['multiple_choice', 'true_false', 'open'])->default('multiple_choice');
            $table->json('options')->nullable()->comment('Opciones para multiple choice: [{\"text\": \"...\", \"is_correct\": false}]');
            $table->string('correct_answer')->nullable()->comment('Respuesta correcta (para true_false o open)');
            $table->integer('points')->default(100);
            $table->integer('time_limit')->default(30)->comment('Segundos para responder');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
