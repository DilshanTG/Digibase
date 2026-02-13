<?php

use App\Http\Controllers\AdminerController;
use App\Http\Controllers\Api\SdkController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// 🗄️ Database Manager (Adminer)
Route::get('/admin/database-manager', [AdminerController::class, 'index'])->name('admin.database');

// JavaScript SDK
Route::get('/sdk/digibase.js', [SdkController::class, 'generate'])->name('sdk.js');

// 🛡️ Self-Healing Storage Link
Route::get('/fix-storage', function () {
    $target = storage_path('app/public');
    $link = public_path('storage');

    if (file_exists($link)) {
        return '✅ Storage link already exists.';
    }

    app('files')->link($target, $link);

    return '✅ Storage link created successfully!';
})->name('fix.storage');

// Development Login Route
if (app()->environment('local', 'testing')) {
    Route::get('/dev/login/{id}', function ($id) {
        auth()->loginUsingId($id);

        return redirect('/admin');
    })->name('dev.login');
}

// 🗄️ INSTANT DATABASE SCHEMA DUMP
// Returns complete database structure as JSON
Route::get('/system/schema-dump', function () {
    $startTime = microtime(true);

    try {
        $tables = Schema::getTables();
        $schema = [];

        foreach ($tables as $table) {
            $tableName = $table['name'];

            // Skip SQLite internal tables
            if (str_starts_with($tableName, 'sqlite_')) {
                continue;
            }

            // Get columns
            $columns = Schema::getColumns($tableName);
            $columnData = [];

            foreach ($columns as $column) {
                $columnData[] = [
                    'name' => $column['name'],
                    'type' => $column['type'],
                    'type_name' => $column['type_name'],
                    'nullable' => $column['nullable'],
                    'default' => $column['default'],
                    'auto_increment' => $column['auto_increment'] ?? false,
                    'collation' => $column['collation'] ?? null,
                    'comment' => $column['comment'] ?? null,
                ];
            }

            // Get indexes
            $indexes = Schema::getIndexes($tableName);
            $indexData = [];

            foreach ($indexes as $index) {
                $indexData[] = [
                    'name' => $index['name'],
                    'columns' => $index['columns'],
                    'unique' => $index['unique'],
                    'primary' => $index['primary'] ?? false,
                ];
            }

            // Get foreign keys (SQLite specific)
            $foreignKeys = [];
            try {
                $fks = DB::select("PRAGMA foreign_key_list('{$tableName}')");
                foreach ($fks as $fk) {
                    $foreignKeys[] = [
                        'id' => $fk->id,
                        'seq' => $fk->seq,
                        'table' => $fk->table,
                        'from' => $fk->from,
                        'to' => $fk->to,
                        'on_update' => $fk->on_update,
                        'on_delete' => $fk->on_delete,
                        'match' => $fk->match,
                    ];
                }
            } catch (\Exception $e) {
                // Foreign key info might not be available
            }

            // Get row count
            $rowCount = DB::table($tableName)->count();

            $schema[$tableName] = [
                'name' => $tableName,
                'columns' => $columnData,
                'indexes' => $indexData,
                'foreign_keys' => $foreignKeys,
                'row_count' => $rowCount,
                'engine' => $table['engine'] ?? null,
                'collation' => $table['collation'] ?? null,
            ];
        }

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        return response()->json([
            'success' => true,
            'database' => config('database.default'),
            'connection' => config('database.connections.'.config('database.default').'.database'),
            'tables_count' => count($schema),
            'execution_time_ms' => $executionTime,
            'generated_at' => now()->toIso8601String(),
            'schema' => $schema,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'error_code' => 'SCHEMA_DUMP_FAILED',
        ], 500);
    }
})->name('system.schema-dump');
