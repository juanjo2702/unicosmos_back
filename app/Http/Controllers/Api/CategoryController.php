<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryController extends BaseController
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = Category::with(['creator', 'questions'])->where('is_active', true);

            // Solo admin puede ver todas las categorías (inactivas también)
            if ($user->role === 'admin') {
                $query = Category::with(['creator', 'questions']);
            }

            $categories = $query->orderBy('name')->get();

            return $this->successResponse($categories);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al listar categorías: '.$e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if (! in_array($user->role, ['admin', 'presenter'])) {
                return $this->errorResponse('Solo administradores o presentadores pueden crear categorías', 403);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:categories,name',
                'description' => 'nullable|string',
                'color' => 'nullable|string|max:7',
                'icon' => 'nullable|string|max:50',
            ]);

            $category = Category::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'color' => $validated['color'] ?? '#3B82F6', // Azul por defecto
                'icon' => $validated['icon'] ?? '📚', // Libro por defecto
                'created_by' => $user->id,
                'is_active' => true,
            ]);

            return $this->successResponse($category, 'Categoría creada exitosamente', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al crear categoría: '.$e->getMessage(), 500);
        }
    }

    public function show(Category $category)
    {
        try {
            $category->load(['creator', 'questions' => function ($query) {
                $query->where('is_active', true)->orderBy('points');
            }]);

            return $this->successResponse($category);
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener categoría: '.$e->getMessage(), 500);
        }
    }

    public function update(Request $request, Category $category)
    {
        try {
            $user = $request->user();

            if (! in_array($user->role, ['admin', 'presenter'])) {
                return $this->errorResponse('Solo administradores o presentadores pueden actualizar categorías', 403);
            }

            $validated = $request->validate([
                'name' => 'string|max:255|unique:categories,name,'.$category->id,
                'description' => 'nullable|string',
                'color' => 'nullable|string|max:7',
                'icon' => 'nullable|string|max:50',
                'is_active' => 'boolean',
            ]);

            $category->update($validated);

            return $this->successResponse($category, 'Categoría actualizada exitosamente');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Error al actualizar categoría: '.$e->getMessage(), 500);
        }
    }

    public function destroy(Category $category)
    {
        try {
            $user = request()->user();

            if ($user->role !== 'admin') {
                return $this->errorResponse('Solo administradores pueden eliminar categorías', 403);
            }

            // Verificar si la categoría tiene preguntas activas
            if ($category->questions()->where('is_active', true)->exists()) {
                return $this->errorResponse('No se puede eliminar la categoría porque tiene preguntas activas', 400);
            }

            $category->delete();

            return $this->successResponse(null, 'Categoría eliminada exitosamente');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar categoría: '.$e->getMessage(), 500);
        }
    }

    public function toggleActive(Category $category)
    {
        try {
            $user = request()->user();

            if (! in_array($user->role, ['admin', 'presenter'])) {
                return $this->errorResponse('Solo administradores o presentadores pueden cambiar el estado de categorías', 403);
            }

            $category->update(['is_active' => ! $category->is_active]);

            $status = $category->is_active ? 'activada' : 'desactivada';

            return $this->successResponse($category, "Categoría {$status} exitosamente");
        } catch (\Exception $e) {
            return $this->errorResponse('Error al cambiar estado de categoría: '.$e->getMessage(), 500);
        }
    }

    public function getQuestions(Category $category)
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
