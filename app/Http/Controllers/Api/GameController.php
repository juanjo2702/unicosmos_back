<?php

namespace App\Http\Controllers\Api;

use App\Events\GameStarted;
use App\Models\Game;
use App\Models\Question;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GameController extends BaseController
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = Game::with(['creator', 'teams', 'currentRound', 'currentQuestion.category'])->withCount('teams');

            if ($user->role === 'presenter') {
                $query->where('created_by', $user->id);
            } elseif ($user->role === 'player') {
                $query->whereHas('teams.members', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
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

            if (! in_array($user->role, ['presenter', 'admin'], true)) {
                return $this->errorResponse('Solo presentadores o administradores pueden crear juegos', 403);
            }

            $request->merge([
                'category_ids' => $request->input(
                    'category_ids',
                    $request->filled('category_id') ? [$request->input('category_id')] : []
                ),
            ]);

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'max_players_per_team' => 'integer|min:1|max:10',
                'max_teams' => 'integer|min:1|max:20',
                'category_ids' => 'required|array|min:1',
                'category_ids.*' => 'integer|exists:categories,id',
                'rounds_count' => 'integer|min:1|max:10',
                'time_per_question' => 'integer|min:5|max:120',
                'response_time_limit' => 'integer|min:5|max:120',
            ]);

            $settings = [
                'description' => $validated['description'] ?? null,
                'max_players_per_team' => $validated['max_players_per_team'] ?? 4,
                'max_teams' => $validated['max_teams'] ?? 10,
                'category_ids' => array_values($validated['category_ids']),
                'rounds_count' => $validated['rounds_count'] ?? 3,
                'response_time_limit' => $validated['response_time_limit'] ?? 15,
                'current_question_index' => -1,
                'question_started_at' => null,
                'buzzer_unlock_at' => null,
                'answer_started_at' => null,
                'answer_ends_at' => null,
                'answer_team_id' => null,
                'question_payloads' => [],
            ];

            $game = Game::create([
                'name' => $validated['title'],
                'code' => Str::upper(Str::random(6)),
                'status' => 'pending',
                'created_by' => $user->id,
                'settings' => $settings,
                'time_per_question' => $validated['time_per_question'] ?? 30,
                'max_players' => ($validated['max_players_per_team'] ?? 4) * ($validated['max_teams'] ?? 10),
                'is_accepting_buzzers' => false,
            ]);

            return $this->successResponse(
                $this->decorateGame($game->load(['creator']), true),
                'Juego creado exitosamente',
                201
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear juego: '.$e->getMessage(), 500);
        }
    }

    public function show(Request $request, Game $game)
    {
        try {
            $game = $this->syncBuzzerAvailability(
                $game->load([
                    'creator',
                    'teams.players',
                    'currentQuestion.category',
                    'currentRound',
                ])
            );

            return $this->successResponse(
                $this->decorateGame($game, $this->shouldIncludeCorrectOption($request, $game))
            );
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

            $request->merge([
                'category_ids' => $request->input(
                    'category_ids',
                    $request->filled('category_id') ? [$request->input('category_id')] : null
                ),
            ]);

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|in:pending,active,paused,finished,cancelled',
                'current_round_id' => 'nullable|exists:game_rounds,id',
                'time_per_question' => 'sometimes|integer|min:5|max:120',
                'response_time_limit' => 'sometimes|integer|min:5|max:120',
                'max_players_per_team' => 'sometimes|integer|min:1|max:10',
                'max_teams' => 'sometimes|integer|min:1|max:20',
                'category_ids' => 'nullable|array|min:1',
                'category_ids.*' => 'integer|exists:categories,id',
            ]);

            $updateData = [];

            if (array_key_exists('title', $validated)) {
                $updateData['name'] = $validated['title'];
            }

            if (array_key_exists('status', $validated)) {
                $updateData['status'] = $validated['status'];
            }

            if (array_key_exists('current_round_id', $validated)) {
                $updateData['current_round'] = $validated['current_round_id'];
            }

            if (array_key_exists('time_per_question', $validated)) {
                $updateData['time_per_question'] = $validated['time_per_question'];
            }

            $settings = $game->settings ?? [];

            if (array_key_exists('description', $validated)) {
                $settings['description'] = $validated['description'];
            }

            if (array_key_exists('max_players_per_team', $validated)) {
                $settings['max_players_per_team'] = $validated['max_players_per_team'];
            }

            if (array_key_exists('max_teams', $validated)) {
                $settings['max_teams'] = $validated['max_teams'];
                $updateData['max_players'] = ($settings['max_players_per_team'] ?? 4) * $validated['max_teams'];
            }

            if (array_key_exists('category_ids', $validated) && $validated['category_ids']) {
                $settings['category_ids'] = array_values($validated['category_ids']);
            }

            if (array_key_exists('response_time_limit', $validated)) {
                $settings['response_time_limit'] = $validated['response_time_limit'];
            }

            $updateData['settings'] = $settings;

            $game->update($updateData);

            return $this->successResponse(
                $this->decorateGame(
                    $game->fresh()->load(['creator', 'currentQuestion.category']),
                    $this->shouldIncludeCorrectOption($request, $game)
                ),
                'Juego actualizado exitosamente'
            );
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

            if (! in_array($game->status, ['pending', 'paused'], true)) {
                return $this->errorResponse('El juego ya ha comenzado', 400);
            }

            $request->merge([
                'team_name' => $request->input('team_name', $request->input('teamName')),
                'team_color' => $request->input('team_color', $request->input('color')),
            ]);

            $validated = $request->validate([
                'team_name' => 'required|string|max:255',
                'team_color' => 'nullable|string|max:7',
            ]);

            if ($user->team && (int) $user->team->game_id === (int) $game->id) {
                return $this->successResponse([
                    'game' => $game->load(['creator', 'teams.players', 'currentQuestion.category']),
                    'team' => $user->team->load('players'),
                ], 'Ya estás unido a este juego');
            }

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
                'captain_id' => $user->id,
                'join_code' => Str::upper(Str::random(8)),
            ]);

            $user->update(['team_id' => $team->id]);

            return $this->successResponse([
                'game' => $game->fresh()->load(['creator', 'teams.players', 'currentQuestion.category']),
                'team' => $team->load('players'),
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

            if (! $this->prepareGameQuestions($game)) {
                return $this->errorResponse('No hay preguntas activas para las categorías seleccionadas', 400);
            }

            $game->update([
                'status' => 'active',
                'started_at' => now(),
                'is_accepting_buzzers' => false,
            ]);

            $this->activateQuestion($game, 0);

            $freshGame = $this->decorateGame(
                $this->syncBuzzerAvailability(
                    $game->fresh()->load(['teams.players', 'creator', 'currentQuestion.category'])
                ),
                true
            );

            event(new GameStarted($game->fresh()->load(['teams.players', 'creator', 'currentQuestion.category'])));

            return $this->successResponse($freshGame, '¡El juego ha comenzado!');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al iniciar juego: '.$e->getMessage(), 500);
        }
    }

    public function nextQuestion(Game $game)
    {
        try {
            $user = request()->user();

            if ($game->created_by !== $user->id && $user->role !== 'admin') {
                return $this->errorResponse('No tienes permiso para cambiar la pregunta', 403);
            }

            if (! in_array($game->status, ['active', 'paused'], true)) {
                return $this->errorResponse('El juego aún no está activo', 400);
            }

            $nextIndex = (int) data_get($game->settings, 'current_question_index', -1) + 1;
            $question = $this->activateQuestion($game, $nextIndex);

            if (! $question) {
                $game->update([
                    'status' => 'finished',
                    'finished_at' => now(),
                    'current_question_id' => null,
                    'is_accepting_buzzers' => false,
                ]);

                return $this->successResponse(
                    $this->decorateGame($game->fresh()->load(['teams.players', 'creator']), true),
                    'No quedan más preguntas. El juego ha terminado.'
                );
            }

            return $this->successResponse(
                $this->decorateGame(
                    $this->syncBuzzerAvailability(
                        $game->fresh()->load(['teams.players', 'creator', 'currentQuestion.category'])
                    ),
                    true
                ),
                'Nueva pregunta activada'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Error al avanzar de pregunta: '.$e->getMessage(), 500);
        }
    }

    public function lobby(Request $request, Game $game)
    {
        try {
            $game = $this->decorateGame(
                $this->syncBuzzerAvailability(
                    $game->load([
                        'teams' => function ($query) {
                            $query->withCount('players')->with('players');
                        },
                        'creator',
                        'currentQuestion.category',
                    ])
                ),
                $this->shouldIncludeCorrectOption($request, $game)
            );

            $pressedState = Cache::get("buzzer:{$game->id}:pressed");
            $manualLock = (bool) Cache::get("buzzer:{$game->id}:locked", false);
            $unlockAt = data_get($game->settings, 'buzzer_unlock_at');
            $unlockAtDate = $unlockAt ? Carbon::parse($unlockAt) : null;
            $remainingSeconds = $unlockAtDate && $unlockAtDate->isFuture()
                ? now()->diffInSeconds($unlockAtDate)
                : 0;
            $responseEndsAt = data_get($game->settings, 'answer_ends_at');
            $responseEndsAtDate = $responseEndsAt ? Carbon::parse($responseEndsAt) : null;
            $responseRemainingSeconds = $responseEndsAtDate && $responseEndsAtDate->isFuture()
                ? now()->diffInSeconds($responseEndsAtDate)
                : 0;

            return $this->successResponse([
                'game' => $game,
                'question' => [
                    'current' => $game->currentQuestion,
                    'index' => max(0, (int) data_get($game->settings, 'current_question_index', 0) + 1),
                    'total' => $game->questions()->count(),
                    'startedAt' => data_get($game->settings, 'question_started_at'),
                ],
                'buzzer' => [
                    'pressed' => (bool) $pressedState,
                    'teamId' => $pressedState['teamId'] ?? null,
                    'timestamp' => $pressedState['timestamp'] ?? null,
                    'locked' => $manualLock,
                    'acceptingBuzzers' => ! $manualLock && (bool) $game->is_accepting_buzzers,
                    'unlockAt' => $unlockAt,
                    'remainingSeconds' => $remainingSeconds,
                    'countdownSeconds' => (int) $game->time_per_question,
                    'responseTimeLimit' => $this->getResponseTimeLimit($game),
                    'responseStartedAt' => data_get($game->settings, 'answer_started_at'),
                    'responseEndsAt' => $responseEndsAt,
                    'responseRemainingSeconds' => $responseRemainingSeconds,
                    'answeringTeamId' => data_get($game->settings, 'answer_team_id'),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener lobby: '.$e->getMessage(), 500);
        }
    }

    private function prepareGameQuestions(Game $game): bool
    {
        if ($game->questions()->exists()) {
            return true;
        }

        $categoryIds = $this->getSelectedCategoryIds($game);
        if (empty($categoryIds)) {
            return false;
        }

        $questions = Question::query()
            ->where('is_active', true)
            ->where('type', 'multiple_choice')
            ->whereIn('category_id', $categoryIds)
            ->orderBy('category_id')
            ->orderBy('points')
            ->get();

        if ($questions->isEmpty()) {
            return false;
        }

        $settings = $game->settings ?? [];
        $questionPayloads = [];

        foreach ($questions as $index => $question) {
            $game->questions()->attach($question->id, [
                'order' => $index + 1,
                'status' => 'pending',
            ]);

            $questionPayloads[$question->id] = $this->buildQuestionPresentation($question);
        }

        $settings['question_payloads'] = $questionPayloads;
        $game->update(['settings' => $settings]);

        return true;
    }

    private function activateQuestion(Game $game, int $index): ?Question
    {
        $questions = $game->questions()->with('category')->orderBy('game_question.order')->get()->values();

        if (! isset($questions[$index])) {
            return null;
        }

        if ($game->current_question_id) {
            $game->questions()->updateExistingPivot($game->current_question_id, [
                'status' => 'skipped',
                'answered_at' => now(),
            ]);
        }

        $question = $questions[$index];

        $game->questions()->updateExistingPivot($question->id, [
            'status' => 'asked',
            'asked_at' => now(),
            'answered_at' => null,
            'time_taken' => null,
        ]);

        Cache::forget("buzzer:{$game->id}:pressed");
        Cache::forget("buzzer:{$game->id}:locked");

        $settings = $game->settings ?? [];
        $settings['current_question_index'] = $index;
        $settings['question_started_at'] = now()->toISOString();
        $settings['buzzer_unlock_at'] = now()->addSeconds($game->time_per_question)->toISOString();
        $settings['answer_started_at'] = null;
        $settings['answer_ends_at'] = null;
        $settings['answer_team_id'] = null;

        $game->update([
            'current_question_id' => $question->id,
            'settings' => $settings,
            'is_accepting_buzzers' => false,
        ]);

        return $question;
    }

    private function syncBuzzerAvailability(Game $game): Game
    {
        $manualLock = (bool) Cache::get("buzzer:{$game->id}:locked", false);
        $unlockAt = data_get($game->settings, 'buzzer_unlock_at');
        $shouldAcceptBuzzers = false;

        if (
            ! $manualLock &&
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

    private function getSelectedCategoryIds(Game $game): array
    {
        return array_values(array_filter(data_get($game->settings, 'category_ids', [])));
    }

    private function shouldIncludeCorrectOption(Request $request, Game $game): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'presenter' && (int) $game->created_by === (int) $user->id;
    }

    private function decorateGame(Game $game, bool $includeCorrectOption = false): Game
    {
        if (! $game->relationLoaded('currentQuestion') || ! $game->currentQuestion) {
            return $game;
        }

        $game->setRelation(
            'currentQuestion',
            $this->decorateQuestionForGame($game, $game->currentQuestion, $includeCorrectOption)
        );

        $game->setAttribute('current_question', $game->currentQuestion);

        return $game;
    }

    private function decorateQuestionForGame(Game $game, Question $question, bool $includeCorrectOption = false): Question
    {
        $presentation = $this->getQuestionPresentation($game, $question);

        $question->setAttribute('options', $presentation['options']);
        $question->setAttribute('display_options', $presentation['options']);
        $question->setAttribute('response_time_limit', $this->getResponseTimeLimit($game));

        if ($includeCorrectOption) {
            $question->setAttribute('correct_option_key', $presentation['correct_option_key']);
            $question->setAttribute('correct_option_text', $presentation['correct_option_text']);
        }

        return $question;
    }

    private function getQuestionPresentation(Game $game, Question $question): array
    {
        $storedPresentation = data_get($game->settings, "question_payloads.{$question->id}");

        if ($storedPresentation) {
            return $storedPresentation;
        }

        $presentation = $this->buildQuestionPresentation($question);
        $settings = $game->settings ?? [];
        $settings['question_payloads'][$question->id] = $presentation;
        $game->settings = $settings;
        $game->save();

        return $presentation;
    }

    private function buildQuestionPresentation(Question $question): array
    {
        if ($question->type !== 'multiple_choice') {
            return [
                'options' => [],
                'correct_option_key' => null,
                'correct_option_text' => null,
            ];
        }

        $options = collect($question->options ?? [])
            ->shuffle()
            ->values()
            ->map(function (array $option, int $index) {
                return [
                    'key' => chr(65 + $index),
                    'text' => (string) ($option['text'] ?? ''),
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                ];
            })
            ->values();

        $correctOption = $options->firstWhere('is_correct', true);

        return [
            'options' => $options->map(fn (array $option) => [
                'key' => $option['key'],
                'text' => $option['text'],
            ])->values()->all(),
            'correct_option_key' => $correctOption['key'] ?? null,
            'correct_option_text' => $correctOption['text'] ?? null,
        ];
    }

    private function getResponseTimeLimit(Game $game): int
    {
        return (int) data_get($game->settings, 'response_time_limit', 15);
    }

    private function generateRandomColor(): string
    {
        return '#'.str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }
}
