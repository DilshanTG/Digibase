<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DynamicField;
use App\Models\DynamicModel;
use App\Services\MigrationGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DynamicModelController extends Controller
{
    /**
     * Get all models for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $models = DynamicModel::where('user_id', $request->user()->id)
            ->with(['fields'])
            ->withCount('fields')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($models);
    }

    /**
     * Get a specific model with its fields and relationships.
     */
    public function show(Request $request, DynamicModel $dynamicModel): JsonResponse
    {
        if ($dynamicModel->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $dynamicModel->load(['fields', 'relationships.relatedModel']);

        return response()->json($dynamicModel);
    }

    /**
     * Create a new model with fields.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|regex:/^[a-zA-Z][a-zA-Z0-9]*$/',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'has_timestamps' => 'boolean',
            'has_soft_deletes' => 'boolean',
            'generate_api' => 'boolean',
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string|max:255|regex:/^[a-z][a-z0-9_]*$/',
            'fields.*.display_name' => 'required|string|max:255',
            'fields.*.type' => 'required|string|in:string,text,richtext,integer,float,decimal,boolean,date,datetime,time,json,enum,select,email,url,phone,slug,uuid,file,image',
            'fields.*.description' => 'nullable|string',
            'fields.*.is_required' => 'boolean',
            'fields.*.is_unique' => 'boolean',
            'fields.*.is_indexed' => 'boolean',
            'fields.*.is_searchable' => 'boolean',
            'fields.*.is_filterable' => 'boolean',
            'fields.*.is_sortable' => 'boolean',
            'fields.*.show_in_list' => 'boolean',
            'fields.*.show_in_detail' => 'boolean',
            'fields.*.default_value' => 'nullable|string',
            'fields.*.options' => 'nullable|array',
        ]);

        // Generate table name from model name
        $tableName = Str::snake(Str::pluralStudly($validated['name']));

        // Check if table already exists
        if (DynamicModel::where('table_name', $tableName)->exists()) {
            return response()->json([
                'message' => 'A model with this name already exists.',
                'errors' => ['name' => ['A model with this name already exists.']]
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Create the model
            $model = DynamicModel::create([
                'user_id' => $request->user()->id,
                'name' => $validated['name'],
                'table_name' => $tableName,
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
                'icon' => $validated['icon'] ?? 'table',
                'has_timestamps' => $validated['has_timestamps'] ?? true,
                'has_soft_deletes' => $validated['has_soft_deletes'] ?? false,
                'generate_api' => $validated['generate_api'] ?? true,
            ]);

            // Create fields
            foreach ($validated['fields'] as $index => $fieldData) {
                DynamicField::create([
                    'dynamic_model_id' => $model->id,
                    'name' => $fieldData['name'],
                    'display_name' => $fieldData['display_name'],
                    'type' => $fieldData['type'],
                    'description' => $fieldData['description'] ?? null,
                    'is_required' => $fieldData['is_required'] ?? false,
                    'is_unique' => $fieldData['is_unique'] ?? false,
                    'is_indexed' => $fieldData['is_indexed'] ?? false,
                    'is_searchable' => $fieldData['is_searchable'] ?? true,
                    'is_filterable' => $fieldData['is_filterable'] ?? true,
                    'is_sortable' => $fieldData['is_sortable'] ?? true,
                    'show_in_list' => $fieldData['show_in_list'] ?? true,
                    'show_in_detail' => $fieldData['show_in_detail'] ?? true,
                    'default_value' => $fieldData['default_value'] ?? null,
                    'options' => $fieldData['options'] ?? null,
                    'order' => $index,
                ]);
            }

            // Generate and run migration
            $this->generateMigration($model);

            DB::commit();

            $model->load('fields');

            return response()->json($model, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create model: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a model.
     */
    public function update(Request $request, DynamicModel $dynamicModel): JsonResponse
    {
        if ($dynamicModel->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'generate_api' => 'boolean',
        ]);

        $dynamicModel->update($validated);

        return response()->json($dynamicModel);
    }

    /**
     * Delete a model and its table.
     */
    public function destroy(Request $request, DynamicModel $dynamicModel): JsonResponse
    {
        if ($dynamicModel->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();

        try {
            // Drop the table if it exists
            if (DB::getSchemaBuilder()->hasTable($dynamicModel->table_name)) {
                DB::getSchemaBuilder()->drop($dynamicModel->table_name);
            }

            // Delete the model (fields and relationships cascade)
            $dynamicModel->delete();

            DB::commit();

            return response()->json(['message' => 'Model deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete model: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available field types.
     */
    public function fieldTypes(): JsonResponse
    {
        $types = [
            ['value' => 'string', 'label' => 'String', 'description' => 'Short text (max 255 chars)', 'icon' => 'font'],
            ['value' => 'text', 'label' => 'Text', 'description' => 'Long text content', 'icon' => 'align-left'],
            ['value' => 'richtext', 'label' => 'Rich Text', 'description' => 'HTML formatted content', 'icon' => 'edit'],
            ['value' => 'integer', 'label' => 'Integer', 'description' => 'Whole numbers', 'icon' => 'hash'],
            ['value' => 'float', 'label' => 'Float', 'description' => 'Decimal numbers', 'icon' => 'percent'],
            ['value' => 'decimal', 'label' => 'Decimal', 'description' => 'Precise decimal numbers', 'icon' => 'dollar-sign'],
            ['value' => 'boolean', 'label' => 'Boolean', 'description' => 'True/False values', 'icon' => 'toggle-left'],
            ['value' => 'date', 'label' => 'Date', 'description' => 'Date without time', 'icon' => 'calendar'],
            ['value' => 'datetime', 'label' => 'DateTime', 'description' => 'Date with time', 'icon' => 'clock'],
            ['value' => 'time', 'label' => 'Time', 'description' => 'Time only', 'icon' => 'clock'],
            ['value' => 'json', 'label' => 'JSON', 'description' => 'JSON data structure', 'icon' => 'code'],
            ['value' => 'enum', 'label' => 'Enum', 'description' => 'Fixed set of values', 'icon' => 'list'],
            ['value' => 'select', 'label' => 'Select', 'description' => 'Dropdown selection', 'icon' => 'chevron-down'],
            ['value' => 'email', 'label' => 'Email', 'description' => 'Email address', 'icon' => 'mail'],
            ['value' => 'url', 'label' => 'URL', 'description' => 'Web URL', 'icon' => 'link'],
            ['value' => 'phone', 'label' => 'Phone', 'description' => 'Phone number', 'icon' => 'phone'],
            ['value' => 'slug', 'label' => 'Slug', 'description' => 'URL-friendly string', 'icon' => 'link-2'],
            ['value' => 'uuid', 'label' => 'UUID', 'description' => 'Unique identifier', 'icon' => 'key'],
            ['value' => 'file', 'label' => 'File', 'description' => 'File upload', 'icon' => 'file'],
            ['value' => 'image', 'label' => 'Image', 'description' => 'Image upload', 'icon' => 'image'],
        ];

        return response()->json($types);
    }

    /**
     * Add new fields to an existing model and update the database table.
     */
    public function addFields(Request $request, DynamicModel $dynamicModel): JsonResponse
    {
        if ($dynamicModel->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string|max:255|regex:/^[a-z][a-z0-9_]*$/',
            'fields.*.display_name' => 'required|string|max:255',
            'fields.*.type' => 'required|string|in:string,text,richtext,integer,float,decimal,boolean,date,datetime,time,json,enum,select,email,url,phone,slug,uuid,file,image',
            'fields.*.description' => 'nullable|string',
            'fields.*.is_required' => 'boolean',
            'fields.*.is_unique' => 'boolean',
            'fields.*.is_indexed' => 'boolean',
            'fields.*.is_searchable' => 'boolean',
            'fields.*.is_filterable' => 'boolean',
            'fields.*.is_sortable' => 'boolean',
            'fields.*.show_in_list' => 'boolean',
            'fields.*.show_in_detail' => 'boolean',
            'fields.*.default_value' => 'nullable|string',
            'fields.*.options' => 'nullable|array',
        ]);

        // Check if any field already exists
        $existingFieldNames = $dynamicModel->fields()->pluck('name')->toArray();
        foreach ($validated['fields'] as $fieldData) {
            if (in_array($fieldData['name'], $existingFieldNames)) {
                return response()->json([
                    'message' => "Field '{$fieldData['name']}' already exists on this model."
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            $newFields = [];
            $lastOrder = $dynamicModel->fields()->max('order') ?? -1;

            foreach ($validated['fields'] as $index => $fieldData) {
                $field = DynamicField::create([
                    'dynamic_model_id' => $dynamicModel->id,
                    'name' => $fieldData['name'],
                    'display_name' => $fieldData['display_name'],
                    'type' => $fieldData['type'],
                    'description' => $fieldData['description'] ?? null,
                    'is_required' => $fieldData['is_required'] ?? false,
                    'is_unique' => $fieldData['is_unique'] ?? false,
                    'is_indexed' => $fieldData['is_indexed'] ?? false,
                    'is_searchable' => $fieldData['is_searchable'] ?? true,
                    'is_filterable' => $fieldData['is_filterable'] ?? true,
                    'is_sortable' => $fieldData['is_sortable'] ?? true,
                    'show_in_list' => $fieldData['show_in_list'] ?? true,
                    'show_in_detail' => $fieldData['show_in_detail'] ?? true,
                    'default_value' => $fieldData['default_value'] ?? null,
                    'options' => $fieldData['options'] ?? null,
                    'order' => $lastOrder + $index + 1,
                ]);
                $newFields[] = $field;
            }

            // Generate "add columns" migration
            $this->generateAddColumnsMigration($dynamicModel, $newFields);

            DB::commit();

            return response()->json([
                'message' => 'Fields added successfully',
                'model' => $dynamicModel->load('fields')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to add fields: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate migration for adding columns to an existing table.
     */
    protected function generateAddColumnsMigration(DynamicModel $model, array $newFields): void
    {
        $tableName = $model->table_name;
        $fieldDefinitions = '';
        foreach ($newFields as $field) {
            $fieldDefinitions .= $this->buildFieldDefinition($field);
        }

        $migrationContent = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            {$fieldDefinitions}
        });
    }

    public function down(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            // Rollback not fully supported for dynamic additions yet
        });
    }
};
PHP;

        $migrationName = date('Y_m_d_His') . '_add_columns_to_' . $tableName . '_table.php';
        $migrationPath = database_path('migrations/' . $migrationName);

        file_put_contents($migrationPath, $migrationContent);

        // Run the migration
        Artisan::call('migrate', [
            '--path' => 'database/migrations/' . $migrationName,
            '--force' => true,
        ]);
    }

    /**
     * Generate migration for a model.
     */
    protected function generateMigration(DynamicModel $model): void
    {
        $model->load('fields');

        $migrationContent = $this->buildMigrationContent($model);
        $migrationName = date('Y_m_d_His') . '_create_' . $model->table_name . '_table.php';
        $migrationPath = database_path('migrations/' . $migrationName);

        file_put_contents($migrationPath, $migrationContent);

        // Run the migration
        Artisan::call('migrate', [
            '--path' => 'database/migrations/' . $migrationName,
            '--force' => true,
        ]);
    }

    /**
     * Build migration file content.
     */
    protected function buildMigrationContent(DynamicModel $model): string
    {
        $tableName = $model->table_name;
        $fields = $model->fields;

        $fieldDefinitions = '';
        foreach ($fields as $field) {
            $fieldDefinitions .= $this->buildFieldDefinition($field);
        }

        $timestamps = $model->has_timestamps ? "\$table->timestamps();\n            " : '';
        $softDeletes = $model->has_soft_deletes ? "\$table->softDeletes();\n            " : '';

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            {$fieldDefinitions}{$timestamps}{$softDeletes}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;
    }

    /**
     * Build field definition for migration.
     */
    protected function buildFieldDefinition(DynamicField $field): string
    {
        $method = match ($field->type) {
            'string', 'email', 'url', 'phone', 'slug' => 'string',
            'text', 'richtext' => 'text',
            'integer' => 'integer',
            'float' => 'float',
            'decimal' => 'decimal',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'dateTime',
            'time' => 'time',
            'json' => 'json',
            'enum', 'select' => 'string',
            'uuid' => 'uuid',
            'file', 'image' => 'string',
            default => 'string',
        };

        $definition = "\$table->{$method}('{$field->name}')";

        if (!$field->is_required) {
            $definition .= '->nullable()';
        }

        if ($field->is_unique) {
            $definition .= '->unique()';
        }

        if ($field->is_indexed) {
            $definition .= '->index()';
        }

        if ($field->default_value !== null && $field->default_value !== '') {
            $defaultValue = match ($field->type) {
                'boolean' => $field->default_value === 'true' || $field->default_value === '1' ? 'true' : 'false',
                'integer', 'float', 'decimal' => $field->default_value,
                default => "'{$field->default_value}'",
            };
            $definition .= "->default({$defaultValue})";
        }

        return $definition . ";\n            ";
    }
}
