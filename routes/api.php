<?php

use App\Controllers\API\AuthController;

// Маршруты аутентификации
Route::post('/api/v1/auth/register', [AuthController::class, 'register']);
Route::post('/api/v1/auth/login', [AuthController::class, 'login']);

Route::prefix('api/v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// Защищенные маршруты
Route::middleware('auth')->group(function () {
    Route::get('/api/v1/courses', [CourseController::class, 'index']);
    Route::get('/api/v1/courses/search', [CourseController::class, 'search']);
    Route::get('/api/v1/parser/statistics', [CourseController::class, 'getParserStatistics']);
    Route::post('/api/v1/parser/run', [CourseController::class, 'runAllParsers']);
}); 