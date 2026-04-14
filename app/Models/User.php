<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar_url',
        'is_active',
        'team_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPresenter(): bool
    {
        return $this->role === 'presenter';
    }

    public function isPlayer(): bool
    {
        return $this->role === 'player';
    }

    public function hasGameAccess(int $gameId): bool
    {
        if ($this->isAdmin() || $this->isPresenter()) {
            return true;
        }

        if ($this->isPlayer()) {
            return $this->team && $this->team->game_id === $gameId;
        }

        return false;
    }

    public function isPresenterOfGame(int $gameId): bool
    {
        if (! $this->isPresenter()) {
            return false;
        }

        // Asumimos que el presentador puede acceder a cualquier juego
        // En una implementación real, se verificaría si es el creador
        return true;
    }

    public function isMemberOfTeam(int $teamId): bool
    {
        return $this->team_id === $teamId;
    }

    public function canPressBuzzer(int $gameId): bool
    {
        if (! $this->isPlayer() || ! $this->team) {
            return false;
        }

        $game = Game::find($gameId);
        if (! $game || ! $game->isAcceptingBuzzers()) {
            return false;
        }

        return $this->team->game_id === $gameId;
    }

    public function getAvatarUrl(): string
    {
        return $this->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&color=fff&background=1e3a8a';
    }
}
