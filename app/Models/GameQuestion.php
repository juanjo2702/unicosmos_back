<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class GameQuestion extends Pivot
{
    protected $table = 'game_question';

    protected $fillable = [
        'game_id',
        'question_id',
        'round_id',
        'order',
        'status',
        'asked_at',
        'answered_at',
        'time_taken',
    ];

    protected $casts = [
        'asked_at' => 'datetime',
        'answered_at' => 'datetime',
        'time_taken' => 'integer',
    ];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function round()
    {
        return $this->belongsTo(GameRound::class);
    }
}
