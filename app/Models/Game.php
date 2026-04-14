<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Game extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'status',
        'created_by',
        'settings',
        'current_round',
        'current_question_id',
        'max_players',
        'time_per_question',
        'is_accepting_buzzers',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_accepting_buzzers' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(GameRound::class);
    }

    public function buzzerPresses(): HasMany
    {
        return $this->hasMany(BuzzerPress::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'game_question')
            ->withPivot(['round_id', 'order', 'status', 'asked_at', 'answered_at', 'time_taken'])
            ->withTimestamps();
    }

    public function currentRound(): BelongsTo
    {
        return $this->belongsTo(GameRound::class, 'current_round', 'round_number')->where('game_id', $this->id);
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAcceptingBuzzers(): bool
    {
        return $this->is_accepting_buzzers && $this->isActive();
    }

    public function generateCode(): string
    {
        return strtoupper(substr(md5(uniqid()), 0, 6));
    }
}
