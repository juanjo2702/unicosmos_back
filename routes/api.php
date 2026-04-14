<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuzzerController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Ruta de prueba pública
Route::get('/test', function () {
    return response()->json(['message' => 'API funcionando correctamente', 'timestamp' => now()]);
});

// Ruta de debug para JSON
Route::post('/debug/json', function (Request $request) {
    Log::info('Debug JSON', [
        'content-type' => $request->header('content-type'),
        'accept' => $request->header('accept'),
        'all' => $request->all(),
        'json' => $request->json()->all(),
        'input' => $request->input(),
    ]);

    return response()->json([
        'headers' => $request->headers->all(),
        'data' => $request->all(),
        'json' => $request->json()->all(),
        'input' => $request->input(),
    ]);
});

// Rutas protegidas con Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });

    // Rutas de buzzer (ya existentes) - requieren autenticación
    Route::prefix('buzzer')->group(function () {
        Route::post('/press', [BuzzerController::class, 'press']);
        Route::post('/reset', [BuzzerController::class, 'reset']);
        Route::post('/lock', [BuzzerController::class, 'lock']);
    });

    // Rutas de juego
    Route::prefix('games')->group(function () {
        Route::get('/', [GameController::class, 'index']);
        Route::post('/', [GameController::class, 'store'])->middleware('role:admin,presenter');
        Route::get('/{game}', [GameController::class, 'show']);
        Route::put('/{game}', [GameController::class, 'update'])->middleware('role:admin,presenter');
        Route::delete('/{game}', [GameController::class, 'destroy'])->middleware('role:admin');
        Route::post('/{game}/join', [GameController::class, 'join']);
        Route::post('/{game}/start', [GameController::class, 'start'])->middleware('role:admin,presenter');
        Route::get('/{game}/lobby', [GameController::class, 'lobby']);
    });

    // Rutas de categorías
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store'])->middleware('role:admin,presenter');
        Route::get('/{category}', [CategoryController::class, 'show']);
        Route::put('/{category}', [CategoryController::class, 'update'])->middleware('role:admin,presenter');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->middleware('role:admin');
        Route::patch('/{category}/toggle', [CategoryController::class, 'toggleActive'])->middleware('role:admin,presenter');
        Route::get('/{category}/questions', [CategoryController::class, 'getQuestions']);
    });

    // Rutas de preguntas
    Route::prefix('questions')->group(function () {
        Route::get('/', [QuestionController::class, 'index']);
        Route::post('/', [QuestionController::class, 'store'])->middleware('role:admin,presenter');
        Route::get('/{question}', [QuestionController::class, 'show']);
        Route::put('/{question}', [QuestionController::class, 'update'])->middleware('role:admin,presenter');
        Route::delete('/{question}', [QuestionController::class, 'destroy'])->middleware('role:admin');
        Route::patch('/{question}/toggle', [QuestionController::class, 'toggleActive'])->middleware('role:admin,presenter');
        Route::get('/types', [QuestionController::class, 'getTypes']);
        Route::get('/difficulties', [QuestionController::class, 'getDifficulties']);
        Route::get('/category/{category}', [QuestionController::class, 'getByCategory']);
    });

    // Rutas de equipos
    Route::prefix('teams')->group(function () {
        Route::get('/{team}', [TeamController::class, 'show']);
        Route::put('/{team}', [TeamController::class, 'update'])->middleware('role:admin,presenter');
        Route::patch('/{team}/score', [TeamController::class, 'updateScore'])->middleware('role:admin,presenter');
    });

});
