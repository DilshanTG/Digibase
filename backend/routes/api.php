<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DynamicDataController;
use App\Http\Controllers\Api\DynamicModelController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dynamic Models (Visual Model Creator)
    Route::get('/models/field-types', [DynamicModelController::class, 'fieldTypes']);
    Route::apiResource('models', DynamicModelController::class)->parameters([
        'models' => 'dynamicModel'
    ]);

    // Dynamic Data API (Auto-generated CRUD for dynamic models)
    Route::get('/data/{tableName}/schema', [DynamicDataController::class, 'schema']);
    Route::get('/data/{tableName}', [DynamicDataController::class, 'index']);
    Route::post('/data/{tableName}', [DynamicDataController::class, 'store']);
    Route::get('/data/{tableName}/{id}', [DynamicDataController::class, 'show']);
    Route::put('/data/{tableName}/{id}', [DynamicDataController::class, 'update']);
    Route::delete('/data/{tableName}/{id}', [DynamicDataController::class, 'destroy']);
});
