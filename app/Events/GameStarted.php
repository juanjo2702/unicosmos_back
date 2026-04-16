<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $gameId;

    public $game;

    public $timestamp;

    public function __construct(Game $game)
    {
        $this->gameId = $game->id;
        $this->game = $game->load(['teams', 'creator', 'currentQuestion.category']);
        $this->decorateCurrentQuestion();
        $this->timestamp = now()->toISOString();
    }

    public function broadcastOn()
    {
        return new Channel('game.'.$this->gameId);
    }

    public function broadcastAs()
    {
        return 'game.started';
    }

    private function decorateCurrentQuestion(): void
    {
        if (! $this->game->currentQuestion) {
            return;
        }

        $presentation = data_get($this->game->settings, "question_payloads.{$this->game->currentQuestion->id}");

        if (! $presentation) {
            return;
        }

        $this->game->currentQuestion->setAttribute('options', $presentation['options'] ?? []);
        $this->game->currentQuestion->setAttribute('display_options', $presentation['options'] ?? []);
        $this->game->setAttribute('current_question', $this->game->currentQuestion);
    }
}
