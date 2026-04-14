<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuzzerPress extends Model
{
    protected $fillable = [
        'game_id',
        'team_id',
        'user_id',
        'question_id',
        'reaction_time_ms',
        'is_valid',
        'pressed_at',
    ];

    protected $casts = [
        'reaction_time_ms' => 'integer',
        'is_valid' => 'boolean',
        'pressed_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
