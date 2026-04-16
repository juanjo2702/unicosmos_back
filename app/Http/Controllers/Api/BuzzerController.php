<?php

namespace App\Http\Controllers\Api;

use App\Events\BuzzerLocked;
use App\Events\BuzzerPressed;
use App\Events\BuzzerReset;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BuzzerController extends Controller
{
    public function press(Request $request)
    {
        $request->validate([
            'gameId' => 'required',
            'teamId' => 'required|integer|exists:teams,id',
        ]);

        $game = $this->resolveGame($request->input('gameId'));
        if (! $game) {
            return response()->json([
                'error' => 'Game not found',
            ], 404);
        }

        $team = Team::query()
            ->whereKey($request->integer('teamId'))
            ->where('game_id', $game->id)
            ->first();

        if (! $team) {
            return response()->json([
                'error' => 'Team does not belong to this game',
            ], 422);
        }

        $game = $this->refreshBuzzerAvailability($game);

        if (! $game->isAcceptingBuzzers()) {
            return response()->json([
                'error' => 'Todavía no se puede pulsar el buzzer',
            ], 409);
        }

        if ($request->user()->role === 'player' && (int) $request->user()->team_id !== (int) $team->id) {
            return response()->json([
                'error' => 'You can only press for your own team',
            ], 403);
        }

        $gameId = (string) $game->id;
        $teamId = (string) $team->id;

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
        $responseEndsAt = now()->addSeconds($this->getResponseTimeLimit($game))->toISOString();

        // Store pressed state
        Cache::put($key, [
            'teamId' => $teamId,
            'timestamp' => $timestamp,
        ], now()->addHours(1));

        $settings = $game->settings ?? [];
        $settings['answer_started_at'] = $timestamp;
        $settings['answer_ends_at'] = $responseEndsAt;
        $settings['answer_team_id'] = (int) $teamId;
        $game->update([
            'settings' => $settings,
            'is_accepting_buzzers' => false,
        ]);

        // Broadcast event
        event(new BuzzerPressed($teamId, $gameId, $timestamp, $responseEndsAt, $this->getResponseTimeLimit($game)));

        return response()->json([
            'success' => true,
            'teamId' => $teamId,
            'timestamp' => $timestamp,
            'responseEndsAt' => $responseEndsAt,
            'responseTimeLimit' => $this->getResponseTimeLimit($game),
            'message' => 'Buzzer pressed successfully',
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'gameId' => 'required',
        ]);

        $game = $this->resolveGame($request->input('gameId'));
        if (! $game) {
            return response()->json([
                'error' => 'Game not found',
            ], 404);
        }

        if (! $this->canControlGame($request, $game)) {
            return response()->json([
                'error' => 'You do not have permission to control this game',
            ], 403);
        }

        $gameId = (string) $game->id;
        $key = "buzzer:{$gameId}:pressed";

        Cache::forget($key);
        $settings = $game->settings ?? [];
        $settings['answer_started_at'] = null;
        $settings['answer_ends_at'] = null;
        $settings['answer_team_id'] = null;
        $game->update(['settings' => $settings]);
        $game = $this->refreshBuzzerAvailability($game);

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
            'gameId' => 'required',
            'locked' => 'boolean',
        ]);

        $game = $this->resolveGame($request->input('gameId'));
        if (! $game) {
            return response()->json([
                'error' => 'Game not found',
            ], 404);
        }

        if (! $this->canControlGame($request, $game)) {
            return response()->json([
                'error' => 'You do not have permission to control this game',
            ], 403);
        }

        $gameId = (string) $game->id;
        $locked = $request->input('locked', true);

        $key = "buzzer:{$gameId}:locked";
        Cache::put($key, $locked, now()->addHours(1));
        $game = $this->refreshBuzzerAvailability($game, $locked);

        // Broadcast lock event
        event(new BuzzerLocked($gameId, $locked));

        return response()->json([
            'success' => true,
            'locked' => $locked,
            'message' => $locked ? 'Buzzer locked' : 'Buzzer unlocked',
        ]);
    }

    private function resolveGame(string|int $identifier): ?Game
    {
        if (is_numeric($identifier)) {
            return Game::find((int) $identifier);
        }

        return Game::where('code', $identifier)->first();
    }

    private function canControlGame(Request $request, Game $game): bool
    {
        $user = $request->user();

        if (! in_array($user->role, ['admin', 'presenter'], true)) {
            return false;
        }

        return $user->role === 'admin' || (int) $game->created_by === (int) $user->id;
    }

    private function refreshBuzzerAvailability(Game $game, ?bool $manualLock = null): Game
    {
        $isManuallyLocked = $manualLock ?? (bool) Cache::get("buzzer:{$game->id}:locked", false);
        $unlockAt = data_get($game->settings, 'buzzer_unlock_at');
        $shouldAcceptBuzzers = false;

        if (
            ! $isManuallyLocked &&
            $game->status === 'active' &&
            $game->current_question_id &&
            $unlockAt &&
            ! Cache::has("buzzer:{$game->id}:pressed")
        ) {
            $shouldAcceptBuzzers = Carbon::parse($unlockAt)->isPast();
        }

        if ((bool) $game->is_accepting_buzzers !== $shouldAcceptBuzzers) {
            $game->is_accepting_buzzers = $shouldAcceptBuzzers;
            $game->save();
        }

        return $game;
    }

    private function getResponseTimeLimit(Game $game): int
    {
        return (int) data_get($game->settings, 'response_time_limit', 15);
    }
}
