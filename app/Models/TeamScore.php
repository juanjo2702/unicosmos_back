<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamScore extends Model
{
    protected $fillable = [
        'team_id',
        'game_id',
        'round_id',
        'score',
        'bonus_points',
        'total_score',
        'rank',
        'answered_correctly',
        'answered_incorrectly',
        'metadata',
    ];

    protected $casts = [
        'score' => 'integer',
        'bonus_points' => 'integer',
        'total_score' => 'integer',
        'rank' => 'integer',
        'answered_correctly' => 'integer',
        'answered_incorrectly' => 'integer',
        'metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(GameRound::class);
    }
}
