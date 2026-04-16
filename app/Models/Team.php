<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'game_id',
        'color',
        'avatar_url',
        'score',
        'captain_id',
        'join_code',
        'is_active',
    ];

    protected $casts = [
        'score' => 'integer',
        'is_active' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function captain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captain_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function players(): HasMany
    {
        return $this->members();
    }

    public function buzzerPresses(): HasMany
    {
        return $this->hasMany(BuzzerPress::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(TeamScore::class);
    }

    public function generateJoinCode(): string
    {
        return strtoupper(substr(md5(uniqid()), 0, 8));
    }
}
