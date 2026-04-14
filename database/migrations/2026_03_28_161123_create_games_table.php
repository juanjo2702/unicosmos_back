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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique()->index();
            $table->enum('status', ['pending', 'active', 'paused', 'finished', 'cancelled'])->default('pending');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->json('settings')->nullable()->comment('Configuración del juego: tiempo por pregunta, rondas, etc.');
            $table->integer('current_round')->default(1);
            $table->integer('current_question_id')->nullable();
            $table->integer('max_players')->default(10);
            $table->integer('time_per_question')->default(30)->comment('Segundos');
            $table->boolean('is_accepting_buzzers')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
