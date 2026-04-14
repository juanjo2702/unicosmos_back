<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'question_text',
        'type',
        'options',
        'correct_answer',
        'points',
        'time_limit',
        'difficulty',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'points' => 'integer',
        'time_limit' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_question')
            ->withPivot(['round_id', 'order', 'status', 'asked_at', 'answered_at', 'time_taken'])
            ->withTimestamps();
    }

    public function getCorrectOptions(): array
    {
        if ($this->type === 'multiple_choice') {
            return array_filter($this->options ?? [], fn ($option) => $option['is_correct'] ?? false);
        }

        return [$this->correct_answer];
    }
}
