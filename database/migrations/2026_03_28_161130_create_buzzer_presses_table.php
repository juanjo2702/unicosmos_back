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
        Schema::create('buzzer_presses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('question_id')->nullable()->constrained('questions')->onDelete('set null');
            $table->integer('reaction_time_ms')->nullable()->comment('Tiempo de reacción en milisegundos');
            $table->boolean('is_valid')->default(true)->comment('Si la pulsación fue válida (no antes de tiempo)');
            $table->timestamp('pressed_at')->useCurrent();
            $table->timestamps();

            $table->index(['game_id', 'pressed_at']);
            $table->index(['team_id', 'pressed_at']);
            $table->unique(['game_id', 'team_id', 'question_id'], 'unique_buzzer_per_question');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buzzer_presses');
    }
};
