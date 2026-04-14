<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameRound extends Model
{
    protected $fillable = [
        'game_id',
        'round_number',
        'name',
        'description',
        'type',
        'points_multiplier',
        'status',
        'settings',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'points_multiplier' => 'float',
        'settings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'game_question')
            ->withPivot(['order', 'status', 'asked_at', 'answered_at', 'time_taken']);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(TeamScore::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
