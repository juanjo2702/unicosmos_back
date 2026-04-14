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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('game_id')->constrained('games')->onDelete('cascade');
            $table->string('color')->default('#1e3a8a')->comment('Color del equipo en hex');
            $table->string('avatar_url')->nullable();
            $table->integer('score')->default(0);
            $table->foreignId('captain_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('join_code')->unique()->nullable()->comment('Código para unirse al equipo');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['game_id', 'score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
