<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DatabaseController;
use App\Http\Controllers\Api\DynamicModelController;
use App\Http\Controllers\Api\CoreDataController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::get('/settings/public', function() {
        if (!function_exists('db_config')) return response()->json([], 503);
        
        return response()->json([
            'app_name' => db_config('branding.site_name') ?? 'Digibase',
            'logo_url' => db_config('branding.site_logo'),
            'features' => [
                'google_login' => db_config('auth.google_enabled') && !empty(db_config('auth.google_client_id')),
                'github_login' => db_config('auth.github_enabled') && !empty(db_config('auth.github_client_id')),
            ]
        ]);
    });
});

// Social OAuth
Route::get('/auth/providers', [AuthController::class, 'getProviders']);
Route::get('/auth/{provider}', [AuthController::class, 'redirectToProvider']);
Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback']);

// ============================================================================
// CORE DATA API (Unified)
// ============================================================================
// 🛡️ Iron Dome: API key validation
// 🩺 Schema Doctor: Validation
// ⚡ Turbo Cache: Caching
// 📡 Live Wire: Real-time
// ============================================================================

// V1 Prefix
Route::prefix('v1')->middleware(['api.key', App\Http\Middleware\ApiRateLimiter::class, App\Http\Middleware\LogApiActivity::class])->group(function () {
    Route::get('/data/{tableName}', [CoreDataController::class, 'index']);
    Route::get('/data/{tableName}/schema', [CoreDataController::class, 'schema']);
    Route::get('/data/{tableName}/{id}', [CoreDataController::class, 'show']);
    
    Route::post('/data/{tableName}', [CoreDataController::class, 'store']);
    Route::put('/data/{tableName}/{id}', [CoreDataController::class, 'update']);
    Route::delete('/data/{tableName}/{id}', [CoreDataController::class, 'destroy']);
});

// LEGACY COMPATIBILITY ROUTING (Mapped to CoreDataController)
// Note: We maintain these routes but point them to the new engine.
Route::middleware(['api.key', 'throttle:60,1', App\Http\Middleware\ApiRateLimiter::class])->group(function () {
    Route::get('/data/{tableName}', [CoreDataController::class, 'index']);
    Route::get('/data/{tableName}/schema', [CoreDataController::class, 'schema']);
    Route::get('/data/{tableName}/{id}', [CoreDataController::class, 'show']);
    
    Route::post('/data/{tableName}', [CoreDataController::class, 'store']);
    Route::put('/data/{tableName}/{id}', [CoreDataController::class, 'update']);
    Route::delete('/data/{tableName}/{id}', [CoreDataController::class, 'destroy']);
});

// Protected routes (Sanctum)
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
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

    // Migration Management
    Route::get('/migrations', [\App\Http\Controllers\Api\MigrationController::class, 'index']);
    Route::post('/migrations/run', [\App\Http\Controllers\Api\MigrationController::class, 'migrate']);
    Route::post('/migrations/rollback', [\App\Http\Controllers\Api\MigrationController::class, 'rollback']);
});
