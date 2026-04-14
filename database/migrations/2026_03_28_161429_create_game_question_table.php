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
        Schema::create('game_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('questions')->onDelete('cascade');
            $table->foreignId('round_id')->nullable()->constrained('game_rounds')->onDelete('set null');
            $table->integer('order')->default(0)->comment('Orden de la pregunta en el juego/ronda');
            $table->enum('status', ['pending', 'asked', 'answered', 'skipped'])->default('pending');
            $table->timestamp('asked_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->integer('time_taken')->nullable()->comment('Tiempo tomado para responder en segundos');
            $table->timestamps();

            $table->unique(['game_id', 'question_id']);
            $table->index(['game_id', 'round_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_question');
    }
};
