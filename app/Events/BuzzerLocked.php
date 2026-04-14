<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BuzzerLocked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $gameId;

    public $locked;

    public function __construct($gameId, $locked)
    {
        $this->gameId = $gameId;
        $this->locked = $locked;
    }

    public function broadcastOn()
    {
        return new Channel('game.'.$this->gameId);
    }

    public function broadcastAs()
    {
        return 'buzzer.locked';
    }
}
