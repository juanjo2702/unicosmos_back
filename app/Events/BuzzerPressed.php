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

    public function __construct($teamId, $gameId, $timestamp)
    {
        $this->teamId = $teamId;
        $this->gameId = $gameId;
        $this->timestamp = $timestamp;
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
