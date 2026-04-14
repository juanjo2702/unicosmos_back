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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'presenter', 'player'])->default('player')->after('email_verified_at');
            $table->string('avatar_url')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('avatar_url');
            $table->foreignId('team_id')->nullable()->after('is_active')->constrained('teams')->onDelete('set null');
            $table->index(['team_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropIndex(['team_id', 'role']);
            $table->dropColumn('team_id');
            $table->dropColumn('is_active');
            $table->dropColumn('avatar_url');
            $table->dropColumn('role');
        });
    }
};
