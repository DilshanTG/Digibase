<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DynamicModel;
use App\Models\DynamicRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseController extends Controller
{
    /**
     * System tables that should be hidden from regular users.
     * These contain sensitive data that shouldn't be exposed.
     */
    protected array $protectedTables = [
        'users',
        'password_reset_tokens',
        'personal_access_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'migrations',
        'roles',
        'permissions',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'dynamic_models',
        'dynamic_fields',
        'dynamic_relationships',
        'webhooks',
        'settings',
        'storage_files',
        'api_keys',
        'db_config',
        'file_system_items',
    ];

    /**
     * Check if a table is protected/sensitive.
     */
    protected function isProtectedTable(string $tableName): bool
    {
        return in_array($tableName, $this->protectedTables);
    }

    /**
     * Get all tables in the database (filters out protected system tables).
     */
    public function tables(Request $request): JsonResponse
    {
        $tables = Schema::getTables();

        $tableInfo = collect($tables)
            ->filter(function ($table) {
                // Filter out protected system tables
                return !$this->isProtectedTable($table['name']);
            })
            ->map(function ($table) {
                $tableName = $table['name'];

                // Get row count
                $rowCount = DB::table($tableName)->count();

                // Check if it's a dynamic model table
                $dynamicModel = DynamicModel::where('table_name', $tableName)->first();

                return [
                    'name' => $tableName,
                    'rows' => $rowCount,
                    'is_dynamic' => $dynamicModel !== null,
                    'dynamic_model_id' => $dynamicModel?->id,
                    'dynamic_model_name' => $dynamicModel?->display_name,
                ];
            })->values();

        return response()->json(['data' => $tableInfo]);
    }

    /**
     * Get table structure/schema.
     */
    public function structure(Request $request, string $tableName): JsonResponse
    {
        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table not found'], 404);
        }

        // Block access to protected system tables
        if ($this->isProtectedTable($tableName)) {
            return response()->json(['message' => 'Access to this table is restricted'], 403);
        }

        $columns = Schema::getColumns($tableName);
        $indexes = Schema::getIndexes($tableName);

        $columnInfo = collect($columns)->map(function ($column) {
            return [
                'name' => $column['name'],
                'type' => $column['type'],
                'nullable' => $column['nullable'],
                'default' => $column['default'],
                'auto_increment' => $column['auto_increment'] ?? false,
            ];
        });

        $indexInfo = collect($indexes)->map(function ($index) {
            return [
                'name' => $index['name'],
                'columns' => $index['columns'],
                'unique' => $index['unique'],
                'primary' => $index['primary'] ?? false,
            ];
        });

        // Check if dynamic model
        $dynamicModel = DynamicModel::where('table_name', $tableName)->first();

        return response()->json([
            'table' => $tableName,
            'columns' => $columnInfo,
            'indexes' => $indexInfo,
            'is_dynamic' => $dynamicModel !== null,
            'dynamic_model' => $dynamicModel ? [
                'id' => $dynamicModel->id,
                'name' => $dynamicModel->name,
                'display_name' => $dynamicModel->display_name,
            ] : null,
        ]);
    }

    /**
     * Get table data with pagination.
     */
    public function data(Request $request, string $tableName): JsonResponse
    {
        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table not found'], 404);
        }

        // Block access to protected system tables
        if ($this->isProtectedTable($tableName)) {
            return response()->json(['message' => 'Access to this table is restricted'], 403);
        }

        $perPage = min($request->get('per_page', 25), 100);
        $page = $request->get('page', 1);
        $sortBy = $request->get('sort', 'id');
        $sortDir = $request->get('direction', 'desc');

        // Get columns to validate sort field
        $columns = collect(Schema::getColumns($tableName))->pluck('name')->toArray();

        if (!in_array($sortBy, $columns)) {
            $sortBy = $columns[0] ?? 'id';
        }

        $query = DB::table($tableName);

        // Apply search if provided
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($columns, $search) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$search}%");
                }
            });
        }

        // Apply column filters
        foreach ($columns as $column) {
            if ($request->has("filter_{$column}") && $request->input("filter_{$column}") !== null) {
                $query->where($column, $request->input("filter_{$column}"));
            }
        }

        $total = $query->count();
        $data = $query
            ->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return response()->json([
            'data' => $data,
            'columns' => $columns,
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Sanitize SQL by removing comments that could be used to bypass keyword detection.
     */
    protected function removeComments(string $sql): string
    {
        // Remove single-line comments (-- and #)
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/#.*$/m', '', $sql);

        // Remove multi-line comments (/* */)
        $sql = preg_replace('/\/\*[\s\S]*?\*\//', '', $sql);

        return $sql;
    }

    /**
     * Execute a read-only SQL query.
     */
    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'sql' => 'required|string|max:5000',
        ]);

        $sql = trim($request->sql);

        // Remove comments that could be used to bypass keyword detection
        $sanitizedSql = $this->removeComments($sql);

        // Normalize whitespace for better pattern matching
        $normalizedSql = preg_replace('/\s+/', ' ', $sanitizedSql);

        // Only allow SELECT queries for safety (must start with SELECT)
        if (!preg_match('/^\s*SELECT\s/i', $normalizedSql)) {
            return response()->json([
                'message' => 'Only SELECT queries are allowed',
            ], 400);
        }

        // Block dangerous keywords
        $dangerous = [
            'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE',
            'GRANT', 'REVOKE', 'EXEC', 'EXECUTE', 'CALL', 'INTO OUTFILE',
            'INTO DUMPFILE', 'LOAD_FILE', 'BENCHMARK', 'SLEEP', 'WAITFOR',
        ];
        foreach ($dangerous as $keyword) {
            // Use word boundary matching to catch keywords
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $normalizedSql)) {
                return response()->json([
                    'message' => "Query contains forbidden keyword: {$keyword}",
                ], 400);
            }
        }

        // Block queries against protected tables
        foreach ($this->protectedTables as $table) {
            if (preg_match('/\b' . preg_quote($table, '/') . '\b/i', $normalizedSql)) {
                return response()->json([
                    'message' => "Access to table '{$table}' is restricted",
                ], 403);
            }
        }

        // Block multiple statements (semicolon followed by another statement)
        if (preg_match('/;\s*\S/', $normalizedSql)) {
            return response()->json([
                'message' => 'Multiple statements are not allowed',
            ], 400);
        }

        try {
            $startTime = microtime(true);
            $results = DB::select($sql);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $columns = [];
            if (!empty($results)) {
                $columns = array_keys((array) $results[0]);
            }

            return response()->json([
                'data' => $results,
                'columns' => $columns,
                'rows_count' => count($results),
                'execution_time_ms' => $executionTime,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Query error: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get database statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $tables = Schema::getTables();

        $totalRows = 0;
        $tableStats = [];

        foreach ($tables as $table) {
            $count = DB::table($table['name'])->count();
            $totalRows += $count;
            $tableStats[] = [
                'name' => $table['name'],
                'rows' => $count,
            ];
        }

        // Sort by row count
        usort($tableStats, fn($a, $b) => $b['rows'] - $a['rows']);

        // Get dynamic models count
        $dynamicModelsCount = DynamicModel::count();

        return response()->json([
            'total_tables' => count($tables),
            'total_rows' => $totalRows,
            'dynamic_models' => $dynamicModelsCount,
            'tables' => array_slice($tableStats, 0, 10), // Top 10 tables
        ]);
    }

    /**
     * Insert a row into a table (for dynamic models only).
     */
    public function insertRow(Request $request, string $tableName): JsonResponse
    {
        // Only allow inserting into dynamic model tables
        $dynamicModel = DynamicModel::where('table_name', $tableName)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$dynamicModel) {
            return response()->json(['message' => 'Unauthorized or table not found'], 403);
        }

        // Only allow fields that are defined in the dynamic model (prevent injection of system columns)
        $allowedFields = $dynamicModel->fields()->pluck('name')->toArray();
        $data = $request->only($allowedFields);

        if ($dynamicModel->has_timestamps) {
            $data['created_at'] = now();
            $data['updated_at'] = now();
        }

        try {
            // Use Eloquent so DynamicRecordObserver fires (cache + real-time)
            $record = new DynamicRecord();
            $record->setDynamicTable($tableName);
            $record->timestamps = false;
            $record->fill($data);
            $record->save();

            return response()->json(['data' => $record->fresh()], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Insert error: ' . $e->getMessage()], 400);
        }
    }

    /**
     * Update a row in a table (for dynamic models only).
     */
    public function updateRow(Request $request, string $tableName, int $id): JsonResponse
    {
        $dynamicModel = DynamicModel::where('table_name', $tableName)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$dynamicModel) {
            return response()->json(['message' => 'Unauthorized or table not found'], 403);
        }

        // Only allow fields that are defined in the dynamic model (prevent injection of system columns)
        $allowedFields = $dynamicModel->fields()->pluck('name')->toArray();
        $data = $request->only($allowedFields);

        if ($dynamicModel->has_timestamps) {
            $data['updated_at'] = now();
        }

        try {
            // Use Eloquent so DynamicRecordObserver fires (cache + real-time)
            $record = (new DynamicRecord())->setDynamicTable($tableName)->findOrFail($id);
            $record->setDynamicTable($tableName);
            $record->timestamps = false;
            $record->update($data);

            return response()->json(['data' => $record->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Update error: ' . $e->getMessage()], 400);
        }
    }

    /**
     * Delete a row from a table (for dynamic models only).
     */
    public function deleteRow(Request $request, string $tableName, int $id): JsonResponse
    {
        $dynamicModel = DynamicModel::where('table_name', $tableName)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$dynamicModel) {
            return response()->json(['message' => 'Unauthorized or table not found'], 403);
        }

        try {
            // Use Eloquent so DynamicRecordObserver fires (cache + real-time)
            $record = (new DynamicRecord())->setDynamicTable($tableName)->findOrFail($id);
            $record->setDynamicTable($tableName);
            $record->timestamps = false;

            if ($dynamicModel->has_soft_deletes) {
                $record->update(['deleted_at' => now()]);
            } else {
                $record->delete();
            }

            return response()->json(['message' => 'Row deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Delete error: ' . $e->getMessage()], 400);
        }
    }
}
