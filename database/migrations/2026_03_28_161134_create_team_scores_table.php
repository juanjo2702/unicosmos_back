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
        Schema::create('team_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->foreignId('round_id')->nullable()->constrained('game_rounds')->onDelete('set null');
            $table->integer('score')->default(0)->comment('Puntos ganados en esta ronda/juego');
            $table->integer('bonus_points')->default(0);
            $table->integer('total_score')->default(0)->comment('Puntuación acumulada total');
            $table->integer('rank')->nullable()->comment('Posición en el ranking');
            $table->integer('answered_correctly')->default(0);
            $table->integer('answered_incorrectly')->default(0);
            $table->json('metadata')->nullable()->comment('Datos adicionales: tiempos, etc.');
            $table->timestamps();

            $table->index(['game_id', 'total_score']);
            $table->index(['round_id', 'team_id']);
            $table->unique(['team_id', 'game_id', 'round_id'], 'unique_score_per_round');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_scores');
    }
};
