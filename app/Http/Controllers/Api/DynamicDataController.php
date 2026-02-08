<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ModelActivity;
use App\Models\DynamicModel;
use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            ->where('generate_api', true)
            ->with('fields')
            ->first();
    }

    /**
     * Extract the ownership field name from an RLS rule like "auth.id == user_id".
     * Returns null if the rule is not an ownership check.
     */
    protected function extractOwnershipField(?string $rule): ?string
    {
        if (empty($rule)) {
            return null;
        }

        $rule = trim(strtolower($rule));

        if (preg_match('/^auth\.id\s*==\s*(\w+)$/', $rule, $matches)) {
            $fieldName = $matches[1];
            if ($fieldName !== 'null') {
                return $fieldName;
            }
        }

        return null;
    }

    /**
     * Validate an RLS rule expression safely (NO eval()!)
     *
     * Supported expressions:
     * - null/empty = Deny (Admin Only)
     * - 'true' = Allow everyone
     * - 'false' = Deny everyone
     * - 'auth.id != null' = Authenticated users only
     * - 'auth.id == user_id' = Owner only (record's user_id matches auth user)
     * - 'auth.id == {field_name}' = Dynamic field ownership check
     *
     * @param string|null $rule The rule expression
     * @param object|null $record The record being accessed (for ownership checks)
     * @return bool Whether access is allowed
     */
    protected function validateRule(?string $rule, ?object $record = null): bool
    {
        // Empty/null rule = Admin only (deny API access)
        if (empty($rule)) {
            return false;
        }

        $rule = trim(strtolower($rule));

        // Literal true/false
        if ($rule === 'true') {
            return true;
        }
        if ($rule === 'false') {
            return false;
        }

        // Get authenticated user ID (via Sanctum)
        $authId = auth('sanctum')->id();

        // Parse the expression safely
        // Replace auth.id with actual value or 'null' string
        $authIdStr = $authId !== null ? (string) $authId : 'null';

        // Handle "auth.id != null" (Authenticated users)
        if ($rule === 'auth.id != null' || $rule === 'auth.id !== null') {
            return $authId !== null;
        }

        // Handle "auth.id == null" (Unauthenticated users only)
        if ($rule === 'auth.id == null' || $rule === 'auth.id === null') {
            return $authId === null;
        }

        // Handle ownership checks like "auth.id == user_id"
        if (preg_match('/^auth\.id\s*(==|===|!=|!==)\s*(\w+)$/', $rule, $matches)) {
            $operator = $matches[1];
            $fieldName = $matches[2];

            // Skip if the field is 'null' (handled above)
            if ($fieldName === 'null') {
                return false;
            }

            // We need a record to check ownership
            if (!$record) {
                // For list/create operations without a specific record,
                // we can't check ownership, so check if user is authenticated
                return $authId !== null;
            }

            // Get the field value from the record
            $fieldValue = null;
            if (is_object($record)) {
                $fieldValue = $record->{$fieldName} ?? null;
            } elseif (is_array($record)) {
                $fieldValue = $record[$fieldName] ?? null;
            }

            // Perform the comparison
            switch ($operator) {
                case '==':
                case '===':
                    return $authId !== null && (string) $authId === (string) $fieldValue;
                case '!=':
                case '!==':
                    return $authId !== null && (string) $authId !== (string) $fieldValue;
            }
        }

        // Handle compound expressions with && or ||
        if (str_contains($rule, '&&')) {
            $parts = array_map('trim', explode('&&', $rule));
            foreach ($parts as $part) {
                if (!$this->validateRule($part, $record)) {
                    return false;
                }
            }
            return true;
        }

        if (str_contains($rule, '||')) {
            $parts = array_map('trim', explode('||', $rule));
            foreach ($parts as $part) {
                if ($this->validateRule($part, $record)) {
                    return true;
                }
            }
            return false;
        }

        // Unknown expression = deny for safety
        return false;
    }

    /**
     * Trigger webhooks for a dynamic model event.
     * 
     * @param int $modelId The dynamic_model_id
     * @param string $event One of: 'created', 'updated', 'deleted'
     * @param array $data The payload to send
     */
    protected function triggerWebhooks(int $modelId, string $event, array $data): void
    {
        // Find all active webhooks for this model
        $webhooks = Webhook::where('dynamic_model_id', $modelId)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            // Check if this webhook should trigger for this event
            if (!$webhook->shouldTrigger($event)) {
                continue;
            }

            // Filter sensitive data
            $recordData = $data['record'] ?? $data;
            if (is_array($recordData)) {
                $sensitiveKeys = ['password', 'token', 'secret', 'key', 'auth', 'credential', 'remember_token'];
                foreach ($sensitiveKeys as $key) {
                    if (isset($recordData[$key])) {
                        unset($recordData[$key]);
                    }
                    foreach ($recordData as $k => $v) {
                        if (Str::contains(strtolower($k), $sensitiveKeys)) {
                            unset($recordData[$k]);
                        }
                    }
                }
            }

            // Build the payload
            $payload = [
                'event' => $event,
                'table' => $data['table'] ?? null,
                'data' => $recordData,
                'timestamp' => now()->toIso8601String(),
            ];

            // Build headers
            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Digibase-Webhook/1.0',
                'X-Webhook-Event' => $event,
            ];

            // Add HMAC signature if secret is set
            $signature = $webhook->generateSignature($payload);
            if ($signature) {
                $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
            }

            // Add custom headers
            if (!empty($webhook->headers)) {
                $headers = array_merge($headers, $webhook->headers);
            }

            // Send webhook asynchronously (fire and forget)
            try {
                Http::timeout(10)
                    ->withHeaders($headers)
                    ->async()
                    ->post($webhook->url, $payload)
                    ->then(
                        function ($response) use ($webhook) {
                            if ($response->successful()) {
                                $webhook->recordSuccess();
                            } else {
                                $webhook->recordFailure();
                                Log::warning("Webhook failed: {$webhook->url}", [
                                    'status' => $response->status(),
                                    'body' => $response->body(),
                                ]);
                            }
                        },
                        function ($exception) use ($webhook) {
                            $webhook->recordFailure();
                            Log::error("Webhook error: {$webhook->url}", [
                                'error' => $exception->getMessage(),
                            ]);
                        }
                    );
            } catch (\Exception $e) {
                // Log but don't fail the main request
                Log::error("Webhook dispatch error: {$webhook->url}", [
                    'error' => $e->getMessage(),
                ]);
                $webhook->recordFailure();
            }
        }
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

        // RLS: Check list_rule
        if (!$this->validateRule($model->list_rule)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        // Initialize Eloquent Query on DynamicRecord
        $query = (new DynamicRecord)->setDynamicTable($tableName)->newQuery();

        // Filter out soft-deleted records
        if ($model->has_soft_deletes) {
            $query->whereNull('deleted_at');
        }

        // RLS: Apply ownership filter for list queries (e.g. "auth.id == user_id")
        $ownershipField = $this->extractOwnershipField($model->list_rule);
        if ($ownershipField) {
            $authId = auth('sanctum')->id();
            $query->where($ownershipField, $authId);
        }

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

        // Hide sensitive fields
        $hiddenFields = $model->fields->where('is_hidden', true)->pluck('name')->toArray();
        $data->getCollection()->each(function ($item) use ($hiddenFields) {
            $item->makeHidden($hiddenFields);
        });

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

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $query = (new DynamicRecord)->setDynamicTable($tableName)->newQuery();

        // Filter out soft-deleted records
        if ($model->has_soft_deletes) {
            $query->whereNull('deleted_at');
        }

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

        // RLS: Check view_rule with record context
        if (!$this->validateRule($model->view_rule, $record)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }

        // Hide sensitive fields
        $hiddenFields = $model->fields->where('is_hidden', true)->pluck('name')->toArray();
        $record->makeHidden($hiddenFields);

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

        // RLS: Check create_rule
        if (!$this->validateRule($model->create_rule)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
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

        // Trigger webhooks
        $this->triggerWebhooks($model->id, 'created', [
            'table' => $tableName,
            'record' => (array) $record,
        ]);

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

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $record = DB::table($tableName)->where('id', $id)->first();

        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        // RLS: Check update_rule with record context
        if (!$this->validateRule($model->update_rule, $record)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
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

        // Trigger webhooks
        $this->triggerWebhooks($model->id, 'updated', [
            'table' => $tableName,
            'record' => (array) $record,
        ]);

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

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $record = DB::table($tableName)->where('id', $id)->first();

        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        // RLS: Check delete_rule with record context
        if (!$this->validateRule($model->delete_rule, $record)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }

        // Capture record data before deletion for webhook
        $recordData = (array) $record;

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

        // Trigger webhooks
        $this->triggerWebhooks($model->id, 'deleted', [
            'table' => $tableName,
            'record' => $recordData,
        ]);

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

        // RLS: Schema access follows list_rule (if you can list, you can see schema)
        if (!$this->validateRule($model->list_rule)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
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
