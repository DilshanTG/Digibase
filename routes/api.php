<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DatabaseController;
use App\Http\Controllers\Api\DynamicDataController;
use App\Http\Controllers\Api\DynamicModelController;
use App\Http\Controllers\Api\StorageController;
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
    Route::post('/models/{dynamicModel}/fields', [DynamicModelController::class, 'addFields']);
    Route::put('/models/{dynamicModel}/fields/{field}', [DynamicModelController::class, 'updateField']);
    Route::delete('/models/{dynamicModel}/fields/{field}', [DynamicModelController::class, 'destroyField']);

    // Dynamic Data API (Auto-generated CRUD for dynamic models)
    Route::get('/data/{tableName}/schema', [DynamicDataController::class, 'schema']);
    Route::get('/data/{tableName}', [DynamicDataController::class, 'index']);
    Route::post('/data/{tableName}', [DynamicDataController::class, 'store']);
    Route::get('/data/{tableName}/{id}', [DynamicDataController::class, 'show']);
    Route::put('/data/{tableName}/{id}', [DynamicDataController::class, 'update']);
    Route::delete('/data/{tableName}/{id}', [DynamicDataController::class, 'destroy']);

    // File Storage
    Route::get('/storage/stats', [StorageController::class, 'stats']);
    Route::get('/storage/buckets', [StorageController::class, 'buckets']);
    Route::get('/storage', [StorageController::class, 'index']);
    Route::post('/storage', [StorageController::class, 'store']);
    Route::get('/storage/{file}', [StorageController::class, 'show']);
    Route::get('/storage/{file}/download', [StorageController::class, 'download'])->name('storage.download');
    Route::put('/storage/{file}', [StorageController::class, 'update']);
    Route::delete('/storage/{file}', [StorageController::class, 'destroy']);

    // Database Explorer
    Route::get('/database/stats', [DatabaseController::class, 'stats']);
    Route::get('/database/tables', [DatabaseController::class, 'tables']);
    Route::get('/database/tables/{tableName}/structure', [DatabaseController::class, 'structure']);
    Route::get('/database/tables/{tableName}/data', [DatabaseController::class, 'data']);
    Route::post('/database/tables/{tableName}/rows', [DatabaseController::class, 'insertRow']);
    Route::put('/database/tables/{tableName}/rows/{id}', [DatabaseController::class, 'updateRow']);
    Route::delete('/database/tables/{tableName}/rows/{id}', [DatabaseController::class, 'deleteRow']);
    Route::post('/database/query', [DatabaseController::class, 'query']);
    // Code Generator
    Route::post('/code/generate', [\App\Http\Controllers\Api\CodeGeneratorController::class, 'generate']);

    // User Management
    Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
    
    // Role & Permission Management
    Route::get('/permissions', [\App\Http\Controllers\Api\RoleController::class, 'permissions']);
    Route::apiResource('roles', \App\Http\Controllers\Api\RoleController::class);

    // API Key Management
    Route::get('/tokens', [\App\Http\Controllers\Api\ApiKeyController::class, 'index']);
    Route::post('/tokens', [\App\Http\Controllers\Api\ApiKeyController::class, 'store']);
    Route::delete('/tokens/{id}', [\App\Http\Controllers\Api\ApiKeyController::class, 'destroy']);

    // Settings Management
    Route::get('/settings', [\App\Http\Controllers\Api\SettingsController::class, 'index']);
    Route::get('/settings/{group}', [\App\Http\Controllers\Api\SettingsController::class, 'show']);
    Route::put('/settings', [\App\Http\Controllers\Api\SettingsController::class, 'update']);

    // Migration Management
    Route::get('/migrations', [\App\Http\Controllers\Api\MigrationController::class, 'index']);
    Route::post('/migrations/run', [\App\Http\Controllers\Api\MigrationController::class, 'migrate']);
    Route::post('/migrations/rollback', [\App\Http\Controllers\Api\MigrationController::class, 'rollback']);
});
