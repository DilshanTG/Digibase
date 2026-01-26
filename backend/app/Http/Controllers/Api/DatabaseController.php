<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DynamicModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseController extends Controller
{
    /**
     * Get all tables in the database.
     */
    public function tables(Request $request): JsonResponse
    {
        $tables = Schema::getTables();

        $tableInfo = collect($tables)->map(function ($table) {
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
     * Execute a read-only SQL query.
     */
    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'sql' => 'required|string|max:5000',
        ]);

        $sql = trim($request->sql);

        // Only allow SELECT queries for safety
        if (!preg_match('/^\s*SELECT\s/i', $sql)) {
            return response()->json([
                'message' => 'Only SELECT queries are allowed',
            ], 400);
        }

        // Block dangerous keywords
        $dangerous = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'GRANT', 'REVOKE'];
        foreach ($dangerous as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $sql)) {
                return response()->json([
                    'message' => "Query contains forbidden keyword: {$keyword}",
                ], 400);
            }
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

        $data = $request->except(['_token']);

        if ($dynamicModel->has_timestamps) {
            $data['created_at'] = now();
            $data['updated_at'] = now();
        }

        try {
            $id = DB::table($tableName)->insertGetId($data);
            $row = DB::table($tableName)->where('id', $id)->first();

            return response()->json(['data' => $row], 201);
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

        $data = $request->except(['_token', 'id']);

        if ($dynamicModel->has_timestamps) {
            $data['updated_at'] = now();
        }

        try {
            DB::table($tableName)->where('id', $id)->update($data);
            $row = DB::table($tableName)->where('id', $id)->first();

            return response()->json(['data' => $row]);
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
            if ($dynamicModel->has_soft_deletes) {
                DB::table($tableName)->where('id', $id)->update(['deleted_at' => now()]);
            } else {
                DB::table($tableName)->where('id', $id)->delete();
            }

            return response()->json(['message' => 'Row deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Delete error: ' . $e->getMessage()], 400);
        }
    }
}
