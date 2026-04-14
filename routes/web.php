<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Ruta para redirección de autenticación (evita error 'Route [login] not defined')
Route::get('/login', function () {
    return response()->json(['error' => 'Unauthenticated.'], 401);
})->name('login');

// Ruta de prueba JSON en web
Route::post('/web-debug/json', function (Request $request) {
    return response()->json([
        'content-type' => $request->header('content-type'),
        'all' => $request->all(),
        'json' => $request->json()->all(),
        'input' => $request->input(),
    ]);
});
