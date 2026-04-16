<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BuzzerPressed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $teamId;

    public $gameId;

    public $timestamp;

    public $responseEndsAt;

    public $responseTimeLimit;

    public function __construct($teamId, $gameId, $timestamp, $responseEndsAt, $responseTimeLimit)
    {
        $this->teamId = $teamId;
        $this->gameId = $gameId;
        $this->timestamp = $timestamp;
        $this->responseEndsAt = $responseEndsAt;
        $this->responseTimeLimit = $responseTimeLimit;
    }

    public function broadcastOn()
    {
        return new Channel('game.'.$this->gameId);
    }

    public function broadcastAs()
    {
        return 'buzzer.pressed';
    }
}
