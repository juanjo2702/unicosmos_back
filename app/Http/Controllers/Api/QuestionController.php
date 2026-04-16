<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuestionController extends BaseController
{
    private array $questionTypes = ['multiple_choice', 'true_false', 'open'];

    private array $difficultyLevels = ['easy', 'medium', 'hard'];

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = Question::with(['category', 'creator'])->where('is_active', true);

            // Filtros
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('difficulty')) {
                $query->where('difficulty', $request->difficulty);
            }

            // Solo admin puede ver todas las preguntas (inactivas también)
            if ($user->role === 'admin') {
                $query = Question::with(['category', 'creator']);

                if ($request->has('show_inactive')) {
                    $query->where('is_active', false);
                }
            }

            $questions = $query->orderBy('points')->paginate(20);

            return $this->successResponse($questions);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al listar preguntas: '.$e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if (! in_array($user->role, ['admin', 'presenter'])) {
                return $this->errorResponse('Solo administradores o presentadores pueden crear preguntas', 403);
            }

            $validated = $this->validateQuestion($request);

            // Preparar opciones según el tipo
            $options = $this->prepareOptions($validated['type'], $validated);

            $question = Question::create([
                'category_id' => $validated['category_id'],
                'question_text' => $validated['question_text'],
                'type' => $validated['type'],
                'options' => $options,
                'correct_answer' => $this->getCorrectAnswer($validated['type'], $validated),
                'points' => $validated['points'] ?? 10,
                'time_limit' => $validated['time_limit'] ?? 30,
                'difficulty' => $validated['difficulty'] ?? 'medium',
                'created_by' => $user->id,
                'is_active' => true,
            ]);

            return $this->successResponse($question, 'Pregunta creada exitosamente', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear pregunta: '.$e->getMessage(), 500);
        }
    }

    public function show(Question $question)
    {
        try {
            $question->load(['category', 'creator']);

            return $this->successResponse($question);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener pregunta: '.$e->getMessage(), 500);
        }
    }

    public function update(Request $request, Question $question)
    {
        try {
            $user = $request->user();

            if (! in_array($user->role, ['admin', 'presenter'])) {
                return $this->errorResponse('Solo administradores o presentadores pueden actualizar preguntas', 403);
            }

            $validated = $this->validateQuestion($request, $question->id);

            // Preparar opciones según el tipo
            $options = $this->prepareOptions($validated['type'], $validated);
            $correctAnswer = $this->getCorrectAnswer($validated['type'], $validated);

            $question->update([
                'category_id' => $validated['category_id'],
                'question_text' => $validated['question_text'],
                'type' => $validated['type'],
                'options' => $options,
                'correct_answer' => $correctAnswer,
                'points' => $validated['points'] ?? $question->points,
                'time_limit' => $validated['time_limit'] ?? $question->time_limit,
                'difficulty' => $validated['difficulty'] ?? $question->difficulty,
                'is_active' => $validated['is_active'] ?? $question->is_active,
            ]);

            return $this->successResponse($question, 'Pregunta actualizada exitosamente');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar pregunta: '.$e->getMessage(), 500);
        }
    }

    public function destroy(Question $question)
    {
        try {
            $user = request()->user();

            if ($user->role !== 'admin') {
                return $this->errorResponse('Solo administradores pueden eliminar preguntas', 403);
            }

            $question->delete();

            return $this->successResponse(null, 'Pregunta eliminada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar pregunta: '.$e->getMessage(), 500);
        }
    }

    public function toggleActive(Question $question)
    {
        try {
            $user = request()->user();

            if (! in_array($user->role, ['admin', 'presenter'])) {
                return $this->errorResponse('Solo administradores o presentadores pueden cambiar el estado de preguntas', 403);
            }

            $question->update(['is_active' => ! $question->is_active]);

            $status = $question->is_active ? 'activada' : 'desactivada';

            return $this->successResponse($question, "Pregunta {$status} exitosamente");
        } catch (\Exception $e) {
            return $this->errorResponse('Error al cambiar estado de pregunta: '.$e->getMessage(), 500);
        }
    }

    public function getTypes()
    {
        try {
            $types = [
                ['id' => 'multiple_choice', 'name' => 'Selección múltiple', 'description' => 'Varias opciones, una o varias correctas'],
                ['id' => 'true_false', 'name' => 'Verdadero/Falso', 'description' => 'Respuesta verdadero o falso'],
                ['id' => 'open', 'name' => 'Pregunta abierta', 'description' => 'Respuesta textual libre'],
            ];

            return $this->successResponse($types);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener tipos de pregunta: '.$e->getMessage(), 500);
        }
    }

    public function getDifficulties()
    {
        try {
            $difficulties = [
                ['id' => 'easy', 'name' => 'Fácil', 'points_range' => '1-10'],
                ['id' => 'medium', 'name' => 'Medio', 'points_range' => '11-20'],
                ['id' => 'hard', 'name' => 'Difícil', 'points_range' => '21-30'],
            ];

            return $this->successResponse($difficulties);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener dificultades: '.$e->getMessage(), 500);
        }
    }

    private function validateQuestion(Request $request, $questionId = null)
    {
        $rules = [
            'category_id' => 'required|exists:categories,id',
            'question_text' => 'required|string|max:1000',
            'type' => 'required|in:'.implode(',', $this->questionTypes),
            'points' => 'integer|min:1|max:100',
            'time_limit' => 'integer|min:5|max:300',
            'difficulty' => 'in:'.implode(',', $this->difficultyLevels),
            'is_active' => 'boolean',
        ];

        // Reglas específicas por tipo
        $type = $request->type;
        if ($type === 'multiple_choice') {
            $rules['options'] = 'required|array|size:4';
            $rules['options.*.text'] = 'required|string|max:500';
            $rules['options.*.is_correct'] = 'required|boolean';
        } elseif ($type === 'true_false') {
            $rules['correct_answer'] = 'required|boolean';
        } elseif ($type === 'open') {
            $rules['correct_answer'] = 'required|string|max:500';
        }

        $validated = $request->validate($rules);

        if ($type === 'multiple_choice') {
            $correctOptionsCount = collect($validated['options'] ?? [])
                ->filter(fn (array $option) => (bool) ($option['is_correct'] ?? false))
                ->count();

            if ($correctOptionsCount !== 1) {
                throw ValidationException::withMessages([
                    'options' => ['Debes marcar exactamente una opcion correcta.'],
                ]);
            }
        }

        return $validated;
    }

    private function prepareOptions(string $type, array $validated): ?array
    {
        if ($type === 'multiple_choice') {
            return $validated['options'] ?? [];
        }

        return null;
    }

    private function getCorrectAnswer(string $type, array $validated)
    {
        if ($type === 'multiple_choice') {
            // Para selección múltiple, el correct_answer puede ser null o índice de opción correcta
            // En este caso, las opciones ya tienen marcado is_correct
            return null;
        }

        return $validated['correct_answer'] ?? null;
    }

    public function getByCategory(Category $category)
    {
        try {
            $questions = $category->questions()
                ->where('is_active', true)
                ->with(['category', 'creator'])
                ->orderBy('points')
                ->get();

            return $this->successResponse($questions);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener preguntas de la categoría: '.$e->getMessage(), 500);
        }
    }
}
