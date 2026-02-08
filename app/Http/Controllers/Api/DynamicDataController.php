<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ModelActivity;
use App\Models\DynamicModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\DynamicRecord;

class DynamicDataController extends Controller
{
    /**
     * Get the dynamic model by table name.
     */
    protected function getModel(string $tableName): ?DynamicModel
    {
        return DynamicModel::where('table_name', $tableName)
            ->where('is_active', true)
            ->with('fields')
            ->first();
    }

    /**
     * Build validation rules from dynamic fields.
     */
    protected function buildValidationRules(DynamicModel $model, bool $isUpdate = false): array
    {
        $rules = [];

        foreach ($model->fields as $field) {
            $fieldRules = [];

            if ($field->is_required && !$isUpdate) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Type-based validation
            switch ($field->type) {
                case 'string':
                case 'slug':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
                    break;
                case 'text':
                case 'richtext':
                    $fieldRules[] = 'string';
                    break;
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'url':
                    $fieldRules[] = 'url';
                    break;
                case 'integer':
                    $fieldRules[] = 'integer';
                    break;
                case 'float':
                case 'decimal':
                    $fieldRules[] = 'numeric';
                    break;
                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'datetime':
                    $fieldRules[] = 'date';
                    break;
                case 'time':
                    $fieldRules[] = 'date_format:H:i:s';
                    break;
                case 'json':
                    $fieldRules[] = 'array';
                    break;
                case 'enum':
                case 'select':
                    if (!empty($field->options)) {
                        $fieldRules[] = 'in:' . implode(',', $field->options);
                    }
                    break;
                case 'uuid':
                    $fieldRules[] = 'uuid';
                    break;
            }

            // Unique validation
            if ($field->is_unique) {
                $fieldRules[] = 'unique:' . $model->table_name . ',' . $field->name;
            }

            $rules[$field->name] = $fieldRules;
        }

        return $rules;
    }

    /**
     * List all records from a dynamic model.
     */
    public function index(Request $request, string $tableName): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        // Check authorization
        if ($model->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        // Initialize Eloquent Query on DynamicRecord
        $query = (new DynamicRecord)->setDynamicTable($tableName)->newQuery();

        // Handle Relationships
        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));
            foreach ($includes as $include) {
                $relationName = trim($include);
                $relDef = $model->relationships()->where('name', $relationName)->first();

                if ($relDef && $relDef->relatedModel) {
                     DynamicRecord::resolveRelationUsing($relationName, function ($instance) use ($relDef) {
                        $relatedTable = $relDef->relatedModel->table_name;

                        $foreignKey = $relDef->foreign_key;
                        $localKey = $relDef->local_key ?? 'id';
                        
                        if ($relDef->type === 'hasMany') {
                            $foreignKey = $foreignKey ?: Str::singular($instance->getTable()) . '_id';
                            
                            $relation = $instance->hasMany(DynamicRecord::class, $foreignKey, $localKey);
                            $relation->getRelated()->setTable($relatedTable);
                            $relation->getQuery()->from($relatedTable);
                            return $relation;

                        } elseif ($relDef->type === 'hasOne') {
                            $foreignKey = $foreignKey ?: Str::singular($instance->getTable()) . '_id';
                            
                            $relation = $instance->hasOne(DynamicRecord::class, $foreignKey, $localKey);
                            $relation->getRelated()->setTable($relatedTable);
                            $relation->getQuery()->from($relatedTable);
                            return $relation;

                        } elseif ($relDef->type === 'belongsTo') {
                            $foreignKey = $foreignKey ?: Str::singular($relDef->relatedModel->table_name) . '_id';
                            // For belongsTo, localKey is the owner key (id on related table)
                            
                            $relation = $instance->belongsTo(DynamicRecord::class, $foreignKey, $localKey, $relDef->name);
                            $relation->getRelated()->setTable($relatedTable);
                            $relation->getQuery()->from($relatedTable);
                            return $relation;
                        }
                     });
                     $query->with($relationName);
                }
            }
        }

        // Search (Optimized: only search in 'is_searchable' fields)
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $searchableFields = $model->fields->where('is_searchable', true)->pluck('name')->toArray();
            
            if (!empty($searchableFields)) {
                $query->where(function ($q) use ($searchableFields, $searchTerm) {
                    foreach ($searchableFields as $field) {
                        $q->orWhere($field, 'LIKE', $searchTerm . '%');
                    }
                });
            }
        }

        // Filters
        foreach ($model->fields->where('is_filterable', true) as $field) {
            if ($request->has($field->name) && $request->input($field->name) !== null) {
                $query->where($field->name, $request->input($field->name));
            }
        }

        // Sorting
        $sortField = $request->get('sort', 'id');
        $sortDirection = $request->get('direction', 'desc');
        $sortableFields = $model->fields->where('is_sortable', true)->pluck('name')->toArray();
        $sortableFields[] = 'id';
        $sortableFields[] = 'created_at';
        $sortableFields[] = 'updated_at';

        if (in_array($sortField, $sortableFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $data = $query->paginate($perPage);

        return response()->json([
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        ]);
    }

    /**
     * Get a single record from a dynamic model.
     */
    public function show(Request $request, string $tableName, int $id): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        if ($model->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $query = (new DynamicRecord)->setDynamicTable($tableName)->newQuery();

        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));
            foreach ($includes as $include) {
                $relationName = trim($include);
                $relDef = $model->relationships()->where('name', $relationName)->first();

                if ($relDef && $relDef->relatedModel) {
                     DynamicRecord::resolveRelationUsing($relationName, function ($instance) use ($relDef) {
                        $relatedTable = $relDef->relatedModel->table_name;
                        
                        $foreignKey = $relDef->foreign_key;
                        $localKey = $relDef->local_key ?? 'id';

                        if ($relDef->type === 'hasMany') {
                            $foreignKey = $foreignKey ?: Str::singular($instance->getTable()) . '_id';
                            
                            $relation = $instance->hasMany(DynamicRecord::class, $foreignKey, $localKey);
                            $relation->getRelated()->setTable($relatedTable);
                            $relation->getQuery()->from($relatedTable);
                            return $relation;

                        } elseif ($relDef->type === 'hasOne') {
                            $foreignKey = $foreignKey ?: Str::singular($instance->getTable()) . '_id';
                            
                            $relation = $instance->hasOne(DynamicRecord::class, $foreignKey, $localKey);
                            $relation->getRelated()->setTable($relatedTable);
                            $relation->getQuery()->from($relatedTable);
                            return $relation;

                        } elseif ($relDef->type === 'belongsTo') {
                            $foreignKey = $foreignKey ?: Str::singular($relDef->relatedModel->table_name) . '_id';
                            
                            $relation = $instance->belongsTo(DynamicRecord::class, $foreignKey, $localKey, $relDef->name);
                            $relation->getRelated()->setTable($relatedTable);
                            $relation->getQuery()->from($relatedTable);
                            return $relation;
                        }
                     });
                     $query->with($relationName);
                }
            }
        }

        $record = $query->find($id);

        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        return response()->json(['data' => $record]);
    }

    /**
     * Create a new record in a dynamic model.
     */
    public function store(Request $request, string $tableName): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        if ($model->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        // Validate input
        $rules = $this->buildValidationRules($model);
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Prepare data
        $data = [];
        foreach ($model->fields as $field) {
            if ($request->has($field->name)) {
                $value = $request->input($field->name);

                // Handle JSON fields
                if ($field->type === 'json' && is_array($value)) {
                    $value = json_encode($value);
                }

                // Handle boolean fields
                if ($field->type === 'boolean') {
                    $value = (bool) $value;
                }

                $data[$field->name] = $value;
            } elseif ($field->default_value !== null) {
                $data[$field->name] = $field->default_value;
            }
        }

        // Add timestamps if enabled
        if ($model->has_timestamps) {
            $data['created_at'] = now();
            $data['updated_at'] = now();
        }

        $id = DB::table($tableName)->insertGetId($data);
        $record = DB::table($tableName)->where('id', $id)->first();

        // Broadcast Activity
        event(new ModelActivity('created', $model->name, $record, $request->user()));

        return response()->json(['data' => $record], 201);
    }

    /**
     * Update a record in a dynamic model.
     */
    public function update(Request $request, string $tableName, int $id): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        if ($model->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $record = DB::table($tableName)->where('id', $id)->first();

        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        // Validate input (update mode)
        $rules = $this->buildValidationRules($model, true);

        // Modify unique rules to ignore current record
        foreach ($rules as $fieldName => &$fieldRules) {
            foreach ($fieldRules as $key => $rule) {
                if (str_starts_with($rule, 'unique:')) {
                    $fieldRules[$key] = $rule . ',' . $id;
                }
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Prepare data
        $data = [];
        foreach ($model->fields as $field) {
            if ($request->has($field->name)) {
                $value = $request->input($field->name);

                if ($field->type === 'json' && is_array($value)) {
                    $value = json_encode($value);
                }

                if ($field->type === 'boolean') {
                    $value = (bool) $value;
                }

                $data[$field->name] = $value;
            }
        }

        // Update timestamp if enabled
        if ($model->has_timestamps && !empty($data)) {
            $data['updated_at'] = now();
        }

        if (!empty($data)) {
            DB::table($tableName)->where('id', $id)->update($data);
        }

        $record = DB::table($tableName)->where('id', $id)->first();

        // Broadcast Activity
        event(new ModelActivity('updated', $model->name, $record, $request->user()));

        return response()->json(['data' => $record]);
    }

    /**
     * Delete a record from a dynamic model.
     */
    public function destroy(Request $request, string $tableName, int $id): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        if ($model->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $record = DB::table($tableName)->where('id', $id)->first();

        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        // Handle soft deletes
        if ($model->has_soft_deletes) {
            DB::table($tableName)->where('id', $id)->update([
                'deleted_at' => now(),
            ]);
        } else {
            DB::table($tableName)->where('id', $id)->delete();
        }

        // Broadcast Activity
        event(new ModelActivity('deleted', $model->name, ['id' => $id], $request->user()));

        return response()->json(['message' => 'Record deleted successfully']);
    }

    /**
     * Get model schema information.
     */
    public function schema(Request $request, string $tableName): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        if ($model->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'model' => [
                'name' => $model->name,
                'display_name' => $model->display_name,
                'table_name' => $model->table_name,
                'description' => $model->description,
                'has_timestamps' => $model->has_timestamps,
                'has_soft_deletes' => $model->has_soft_deletes,
            ],
            'fields' => $model->fields->map(function ($field) {
                return [
                    'name' => $field->name,
                    'display_name' => $field->display_name,
                    'type' => $field->type,
                    'is_required' => $field->is_required,
                    'is_unique' => $field->is_unique,
                    'is_searchable' => $field->is_searchable,
                    'is_filterable' => $field->is_filterable,
                    'is_sortable' => $field->is_sortable,
                    'default_value' => $field->default_value,
                    'options' => $field->options,
                ];
            }),
            'endpoints' => [
                'list' => [
                    'method' => 'GET',
                    'url' => "/api/data/{$tableName}",
                    'description' => 'List all records with pagination, search, and filters',
                ],
                'create' => [
                    'method' => 'POST',
                    'url' => "/api/data/{$tableName}",
                    'description' => 'Create a new record',
                ],
                'show' => [
                    'method' => 'GET',
                    'url' => "/api/data/{$tableName}/{id}",
                    'description' => 'Get a single record by ID',
                ],
                'update' => [
                    'method' => 'PUT',
                    'url' => "/api/data/{$tableName}/{id}",
                    'description' => 'Update a record by ID',
                ],
                'delete' => [
                    'method' => 'DELETE',
                    'url' => "/api/data/{$tableName}/{id}",
                    'description' => 'Delete a record by ID',
                ],
            ],
        ]);
    }
}
