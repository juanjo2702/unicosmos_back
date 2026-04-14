<?php

namespace App\Http\Controllers\Api;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeamController extends BaseController
{
    public function updateScore(Request $request, Team $team)
    {
        try {
            $user = $request->user();

            // Solo administradores o presentadores pueden actualizar puntajes
            if (! in_array($user->role, ['admin', 'presenter'])) {
                return $this->errorResponse('Solo administradores o presentadores pueden actualizar puntajes', 403);
            }

            // Verificar que el usuario sea el presentador del juego del equipo
            $game = $team->game;
            if ($game->created_by !== $user->id && $user->role !== 'admin') {
                return $this->errorResponse('No tienes permiso para actualizar puntajes en este juego', 403);
            }

            $validated = $request->validate([
                'score' => 'required|integer|min:0',
                'action' => 'nullable|in:add,set', // 'add' para sumar, 'set' para establecer
            ]);

            $action = $validated['action'] ?? 'set';
            $score = $validated['score'];

            if ($action === 'add') {
                $team->score += $score;
            } else {
                $team->score = $score;
            }

            $team->save();

            return $this->successResponse($team, 'Puntaje actualizado exitosamente');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar puntaje: '.$e->getMessage(), 500);
        }
    }

    public function show(Team $team)
    {
        try {
            $team->load(['game', 'captain', 'members']);

            return $this->successResponse($team);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener equipo: '.$e->getMessage(), 500);
        }
    }

    public function update(Request $request, Team $team)
    {
        try {
            $user = $request->user();

            if (! in_array($user->role, ['admin', 'presenter'])) {
                return $this->errorResponse('Solo administradores o presentadores pueden actualizar equipos', 403);
            }

            $game = $team->game;
            if ($game->created_by !== $user->id && $user->role !== 'admin') {
                return $this->errorResponse('No tienes permiso para actualizar este equipo', 403);
            }

            $validated = $request->validate([
                'name' => 'string|max:255',
                'color' => 'string|max:7',
                'is_active' => 'boolean',
            ]);

            $team->update($validated);

            return $this->successResponse($team, 'Equipo actualizado exitosamente');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar equipo: '.$e->getMessage(), 500);
        }
    }
}
