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
        Schema::create('game_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->integer('round_number')->default(1);
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['normal', 'bonus', 'lightning', 'final'])->default('normal');
            $table->float('points_multiplier')->default(1.0);
            $table->enum('status', ['pending', 'active', 'completed'])->default('pending');
            $table->json('settings')->nullable()->comment('Configuración específica de la ronda');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'round_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_rounds');
    }
};
