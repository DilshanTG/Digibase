<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MigrationController extends Controller
{
    protected MigrationService $migrationService;

    public function __construct(MigrationService $migrationService)
    {
        $this->migrationService = $migrationService;
    }

    /**
     * Get all migrations status.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->migrationService->getStatus()
        ]);
    }

    /**
     * Run migrations.
     */
    public function migrate(): JsonResponse
    {
        $result = $this->migrationService->migrate();
        return response()->json($result);
    }

    /**
     * Rollback migrations.
     */
    public function rollback(): JsonResponse
    {
        $result = $this->migrationService->rollback();
        return response()->json($result);
    }
}
