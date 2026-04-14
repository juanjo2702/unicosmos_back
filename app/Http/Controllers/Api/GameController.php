<?php

namespace App\Http\Controllers\Api;

use App\Events\GameStarted;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GameController extends BaseController
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = Game::with(['creator', 'teams', 'currentRound']);

            if ($user->role === 'presenter') {
                $query->where('created_by', $user->id);
            } elseif ($user->role === 'player') {
                $query->whereHas('teams', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }

            $games = $query->orderBy('created_at', 'desc')->paginate(10);

            return $this->successResponse($games);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al listar juegos: '.$e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role !== 'presenter' && $user->role !== 'admin') {
                return $this->errorResponse('Solo presentadores o administradores pueden crear juegos', 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'max_players_per_team' => 'integer|min:1|max:10',
                'max_teams' => 'integer|min:1|max:20',
                'category_id' => 'nullable|exists:categories,id',
                'rounds_count' => 'integer|min:1|max:10',
                'time_per_question' => 'integer|min:5|max:120',
            ]);

            $settings = [
                'description' => $validated['description'] ?? null,
                'max_players_per_team' => $validated['max_players_per_team'] ?? 4,
                'max_teams' => $validated['max_teams'] ?? 10,
                'category_id' => $validated['category_id'] ?? null,
                'rounds_count' => $validated['rounds_count'] ?? 3,
            ];

            $game = Game::create([
                'name' => $validated['title'],
                'code' => Str::upper(Str::random(6)),
                'status' => 'pending',
                'created_by' => $user->id,
                'settings' => $settings,
                'time_per_question' => $validated['time_per_question'] ?? 30,
                'max_players' => ($validated['max_players_per_team'] ?? 4) * ($validated['max_teams'] ?? 10),
            ]);

            return $this->successResponse($game, 'Juego creado exitosamente', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear juego: '.$e->getMessage(), 500);
        }
    }

    public function show(Game $game)
    {
        try {
            $game->load(['creator', 'teams.players', 'rounds.questions', 'currentRound']);

            return $this->successResponse($game);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener juego: '.$e->getMessage(), 500);
        }
    }

    public function update(Request $request, Game $game)
    {
        try {
            $user = $request->user();

            if ($game->created_by !== $user->id && $user->role !== 'admin') {
                return $this->errorResponse('No tienes permiso para actualizar este juego', 403);
            }

            $validated = $request->validate([
                'title' => 'string|max:255',
                'description' => 'nullable|string',
                'status' => 'in:pending,active,paused,finished,cancelled',
                'current_round_id' => 'nullable|exists:game_rounds,id',
            ]);

            $updateData = [];
            if (isset($validated['title'])) {
                $updateData['name'] = $validated['title'];
            }
            if (isset($validated['status'])) {
                $updateData['status'] = $validated['status'];
            }
            if (isset($validated['current_round_id'])) {
                $updateData['current_round'] = $validated['current_round_id'];
            }
            if (isset($validated['description'])) {
                $settings = $game->settings ?? [];
                $settings['description'] = $validated['description'];
                $updateData['settings'] = $settings;
            }

            $game->update($updateData);

            return $this->successResponse($game, 'Juego actualizado exitosamente');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar juego: '.$e->getMessage(), 500);
        }
    }

    public function destroy(Game $game)
    {
        try {
            $user = request()->user();

            if ($game->created_by !== $user->id && $user->role !== 'admin') {
                return $this->errorResponse('No tienes permiso para eliminar este juego', 403);
            }

            $game->delete();

            return $this->successResponse(null, 'Juego eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar juego: '.$e->getMessage(), 500);
        }
    }

    public function join(Request $request, Game $game)
    {
        try {
            $user = $request->user();

            if ($game->status !== 'lobby') {
                return $this->errorResponse('El juego ya ha comenzado', 400);
            }

            $validated = $request->validate([
                'team_name' => 'required|string|max:255',
                'team_color' => 'nullable|string|max:7',
            ]);

            $existingTeam = Team::where('game_id', $game->id)
                ->where('name', $validated['team_name'])
                ->first();

            if ($existingTeam) {
                return $this->errorResponse('Ya existe un equipo con ese nombre en este juego', 409);
            }

            if ($game->teams()->count() >= $game->max_teams) {
                return $this->errorResponse('Se ha alcanzado el número máximo de equipos', 400);
            }

            $team = Team::create([
                'game_id' => $game->id,
                'name' => $validated['team_name'],
                'color' => $validated['team_color'] ?? $this->generateRandomColor(),
                'score' => 0,
                'user_id' => $user->id,
            ]);

            $team->players()->attach($user->id);

            return $this->successResponse([
                'game' => $game->load('teams'),
                'team' => $team,
            ], 'Te has unido al juego exitosamente');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al unirse al juego: '.$e->getMessage(), 500);
        }
    }

    public function start(Game $game)
    {
        try {
            $user = request()->user();

            if ($game->created_by !== $user->id && $user->role !== 'admin') {
                return $this->errorResponse('Solo el presentador puede iniciar el juego', 403);
            }

            if ($game->status !== 'pending') {
                return $this->errorResponse('El juego ya ha comenzado o ha finalizado', 400);
            }

            if ($game->teams()->count() < 2) {
                return $this->errorResponse('Se necesitan al menos 2 equipos para comenzar', 400);
            }

            $game->update([
                'status' => 'active',
                'started_at' => now(),
            ]);

            event(new GameStarted($game));

            return $this->successResponse($game, '¡El juego ha comenzado!');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al iniciar juego: '.$e->getMessage(), 500);
        }
    }

    public function lobby(Game $game)
    {
        try {
            $game->load(['teams' => function ($query) {
                $query->withCount('players');
            }, 'creator']);

            return $this->successResponse($game);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener lobby: '.$e->getMessage(), 500);
        }
    }

    private function generateRandomColor()
    {
        return '#'.str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }
}
