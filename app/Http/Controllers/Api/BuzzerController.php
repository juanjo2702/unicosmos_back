<?php

namespace App\Http\Controllers\Api;

use App\Events\BuzzerLocked;
use App\Events\BuzzerPressed;
use App\Events\BuzzerReset;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BuzzerController extends Controller
{
    public function press(Request $request)
    {
        $request->validate([
            'gameId' => 'required|string',
            'teamId' => 'required|string',
        ]);

        $gameId = $request->input('gameId');
        $teamId = $request->input('teamId');

        // Check if buzzer already pressed
        $key = "buzzer:{$gameId}:pressed";
        if (Cache::has($key)) {
            return response()->json([
                'error' => 'Buzzer already pressed',
                'pressedBy' => Cache::get($key),
            ], 409);
        }

        // Check if buzzer locked
        $lockKey = "buzzer:{$gameId}:locked";
        if (Cache::get($lockKey, false)) {
            return response()->json([
                'error' => 'Buzzer is locked',
            ], 423);
        }

        $timestamp = now()->toISOString();

        // Store pressed state
        Cache::put($key, [
            'teamId' => $teamId,
            'timestamp' => $timestamp,
        ], now()->addHours(1));

        // Broadcast event
        event(new BuzzerPressed($teamId, $gameId, $timestamp));

        return response()->json([
            'success' => true,
            'teamId' => $teamId,
            'timestamp' => $timestamp,
            'message' => 'Buzzer pressed successfully',
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'gameId' => 'required|string',
        ]);

        $gameId = $request->input('gameId');
        $key = "buzzer:{$gameId}:pressed";

        Cache::forget($key);

        // Broadcast reset event
        event(new BuzzerReset($gameId));

        return response()->json([
            'success' => true,
            'message' => 'Buzzer reset successfully',
        ]);
    }

    public function lock(Request $request)
    {
        $request->validate([
            'gameId' => 'required|string',
            'locked' => 'boolean',
        ]);

        $gameId = $request->input('gameId');
        $locked = $request->input('locked', true);

        $key = "buzzer:{$gameId}:locked";
        Cache::put($key, $locked, now()->addHours(1));

        // Broadcast lock event
        event(new BuzzerLocked($gameId, $locked));

        return response()->json([
            'success' => true,
            'locked' => $locked,
            'message' => $locked ? 'Buzzer locked' : 'Buzzer unlocked',
        ]);
    }
}
