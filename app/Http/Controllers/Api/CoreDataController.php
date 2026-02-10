<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ModelActivity;
use App\Models\DynamicModel;
use App\Models\Webhook;
use App\Services\UrlValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\DynamicRecord;
use App\Http\Traits\TurboCache;

/**
 * Core Data Controller - Unified API Engine for Digibase
 * 
 * Integrations:
 * - 🛡️ Iron Dome: API key validation with scopes
 * - 🩺 Schema Doctor: Dynamic validation rules
 * - ⚡ Turbo Cache: Automated caching/invalidation
 * - 📡 Live Wire: Real-time event broadcasting via DynamicRecordObserver
 * - 🔒 Transaction Wrapper: Atomic operations for data integrity
 * - 🎯 Type-Safe Casting: Strict type enforcement before database writes
 */
class CoreDataController extends Controller
{
    use TurboCache;

    /**
     * Get the dynamic model by name (slug) OR table_name.
     */
    protected function getModel(string $tableName): ?DynamicModel
    {
        return DynamicModel::where(function($q) use ($tableName) {
                $q->where('name', $tableName)->orWhere('table_name', $tableName);
            })
            ->where('is_active', true)
            ->where('generate_api', true)
            ->with('fields')
            ->first();
    }

    /**
     * Extract the ownership field name from an RLS rule like "auth.id == user_id".
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
     * Validate an RLS rule expression safely.
     */
    protected function validateRule(?string $rule, ?object $record = null): bool
    {
        if (empty($rule)) return false;
        
        $rule = trim(strtolower($rule));
        if ($rule === 'true') return true;
        if ($rule === 'false') return false;

        $authId = auth('sanctum')->id();

        if ($rule === 'auth.id != null' || $rule === 'auth.id !== null') {
            return $authId !== null;
        }
        if ($rule === 'auth.id == null' || $rule === 'auth.id === null') {
            return $authId === null;
        }

        if (preg_match('/^auth\.id\s*(==|!=|===|!==)\s*(\w+)$/', $rule, $matches)) {
            $op = $matches[1];
            $field = $matches[2];
            
            if (!$record) return $authId !== null;

            $val = is_object($record) ? ($record->$field ?? null) : ($record[$field] ?? null);
            
            return match($op) {
                '==' => (string)$authId === (string)$val,
                '===' => (string)$authId === (string)$val,
                '!=' => (string)$authId !== (string)$val,
                '!==' => (string)$authId !== (string)$val,
                default => false,
            };
        }

        if (str_contains($rule, '&&')) {
            $parts = array_map('trim', explode('&&', $rule));
            foreach ($parts as $part) {
                if (!$this->validateRule($part, $record)) return false;
            }
            return true;
        }

        if (str_contains($rule, '||')) {
            $parts = array_map('trim', explode('||', $rule));
            foreach ($parts as $part) {
                if ($this->validateRule($part, $record)) return true;
            }
            return false;
        }

        return false;
    }

    /**
     * Trigger webhooks for a dynamic model event.
     */
    protected function triggerWebhooks(int $modelId, string $event, array $data): void
    {
        $webhooks = Webhook::where('dynamic_model_id', $modelId)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            if (!$webhook->shouldTrigger($event)) {
                continue;
            }

            $urlCheck = UrlValidator::validateWebhookUrl($webhook->url);
            if (!$urlCheck['valid']) {
                Log::warning("Webhook SSRF blocked: {$webhook->url}", [
                    'reason' => $urlCheck['reason'],
                    'webhook_id' => $webhook->id,
                ]);
                continue;
            }

            $recordData = $data['record'] ?? $data;
            if (is_array($recordData)) {
                $sensitiveKeys = ['password', 'token', 'secret', 'key', 'auth', 'credential', 'remember_token'];
                $recordData = array_filter($recordData, function ($v, $k) use ($sensitiveKeys) {
                    $lowerKey = strtolower($k);
                    if (in_array($lowerKey, $sensitiveKeys)) return false;
                    foreach ($sensitiveKeys as $sensitive) {
                        if (str_contains($lowerKey, $sensitive)) return false;
                    }
                    return true;
                }, ARRAY_FILTER_USE_BOTH);
            }

            $payload = [
                'event' => $event,
                'table' => $data['table'] ?? null,
                'data' => $recordData,
                'timestamp' => now()->toIso8601String(),
            ];

            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Digibase-Webhook/1.0',
                'X-Webhook-Event' => $event,
            ];

            $signature = $webhook->generateSignature($payload);
            if ($signature) {
                $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
            }

            if (!empty($webhook->headers)) {
                $headers = array_merge($headers, $webhook->headers);
            }

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
                Log::error("Webhook dispatch error: {$webhook->url}", [
                    'error' => $e->getMessage(),
                ]);
                $webhook->recordFailure();
            }
        }
    }

    /**
     * 🩺 SCHEMA DOCTOR: Build validation rules from dynamic fields.
     */
    protected function buildValidationRules(DynamicModel $model, bool $isUpdate = false, ?int $recordId = null): array
    {
        $rules = [];

        foreach ($model->fields as $field) {
            $fieldRules = [];

            if ($field->is_required && !$isUpdate) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            switch ($field->type) {
                case 'string':
                case 'slug':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
                    break;
                case 'text':
                case 'richtext':
                case 'markdown':
                    $fieldRules[] = 'string';
                    break;
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'url':
                    $fieldRules[] = 'url';
                    break;
                case 'integer':
                case 'bigint':
                    $fieldRules[] = 'integer';
                    break;
                case 'float':
                case 'decimal':
                case 'money':
                    $fieldRules[] = 'numeric';
                    break;
                case 'boolean':
                case 'checkbox':
                    $fieldRules[] = 'boolean';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'datetime':
                case 'timestamp':
                    $fieldRules[] = 'date';
                    break;
                case 'time':
                    $fieldRules[] = 'date_format:H:i:s';
                    break;
                case 'json':
                case 'array':
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

            if ($field->is_unique) {
                $uniqueRule = 'unique:' . $model->table_name . ',' . $field->name;
                if ($isUpdate && $recordId) {
                    $uniqueRule .= ',' . $recordId;
                }
                $fieldRules[] = $uniqueRule;
            }

            if (!empty($field->validation_rules) && is_array($field->validation_rules)) {
                $fieldRules = array_merge($fieldRules, $field->validation_rules);
            }

            $rules[$field->name] = $fieldRules;
        }

        return $rules;
    }

    /**
     * 🎯 TYPE-SAFE CASTING: Cast value to correct PHP type based on DynamicField schema.
     * Prevents SQLite type-affinity issues.
     */
    protected function castValue(mixed $value, \App\Models\DynamicField $field): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field->type) {
            'integer', 'bigint' => (int) $value,
            'float', 'decimal', 'money' => (float) $value,
            'boolean', 'checkbox' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'json', 'array' => is_array($value) ? json_encode($value) : $value,
            'date' => is_string($value) ? date('Y-m-d', strtotime($value)) : $value,
            'datetime', 'timestamp' => is_string($value) ? date('Y-m-d H:i:s', strtotime($value)) : $value,
            'time' => is_string($value) ? date('H:i:s', strtotime($value)) : $value,
            default => $value,
        };
    }

    /**
     * 🔒 TRANSACTION WRAPPER: Execute mutation in atomic transaction.
     *
     * Uses DB::transaction() with 5 retry attempts to handle SQLite
     * "database is locked" errors under concurrent write load.
     * Each retry is automatically attempted by Laravel's transaction handler.
     */
    protected function executeInTransaction(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            return $callback();
        }, 5);
    }


    /**
     * List all records from a dynamic model.
     * ⚡ TURBO CACHE: Cached with automatic invalidation
     */
    public function index(Request $request, string $tableName): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        if (!$this->validateRule($model->list_rule)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }
        
        $tableName = $model->table_name;

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        return $this->cached($tableName, $request, function () use ($request, $model, $tableName) {
            return $this->executeIndexQuery($request, $model, $tableName);
        });
    }

    /**
     * Execute the actual index query (extracted for caching).
     */
    protected function executeIndexQuery(Request $request, DynamicModel $model, string $tableName): JsonResponse
    {
        $query = (new DynamicRecord)->setDynamicTable($tableName)->newQuery();

        if ($model->has_soft_deletes) {
            $query->whereNull('deleted_at');
        }

        $ownershipField = $this->extractOwnershipField($model->list_rule);
        if ($ownershipField) {
            $authId = auth('sanctum')->id();
            $query->where($ownershipField, $authId);
        }

        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));
            foreach ($includes as $include) {
                $relationName = trim($include);
                $relDef = $model->relationships()->where('name', $relationName)->first();

                if ($relDef && $relDef->relatedModel) {
                     // Namespace relation key with table name to prevent global
                     // state collisions in Octane/Swoole (Bug #4 fix)
                     $uniqueRelKey = $tableName . '__' . $relationName;

                     DynamicRecord::resolveRelationUsing($uniqueRelKey, function ($instance) use ($relDef) {
                        $relatedTable = $relDef->relatedModel->table_name;
                        $foreignKey = $relDef->foreign_key;
                        $localKey = $relDef->local_key ?? 'id';

                        $qualify = fn($col, $table) => str_contains($col, '.') ? $col : "$table.$col";

                        if ($relDef->type === 'hasMany') {
                            $foreignKey = $foreignKey ?: Str::singular($instance->getTable()) . '_id';
                            $foreignKey = $qualify($foreignKey, $relatedTable);

                            $relation = $instance->hasMany(DynamicRecord::class, $foreignKey, $localKey);
                            $relation->getRelated()->setTable($relatedTable);
                            $relation->getQuery()->from($relatedTable);
                            return $relation;
                        }
                        elseif ($relDef->type === 'belongsTo') {
                            $foreignKey = $foreignKey ?: Str::singular($relDef->relatedModel->table_name) . '_id';

                            $relation = $instance->belongsTo(DynamicRecord::class, $foreignKey, $localKey, $relDef->name);
                            $relation->getRelated()->setTable($relatedTable);
                            $relation->getQuery()->from($relatedTable);
                            return $relation;
                        }
                        elseif ($relDef->type === 'hasOne') {
                            $foreignKey = $foreignKey ?: Str::singular($instance->getTable()) . '_id';
                            $foreignKey = $qualify($foreignKey, $relatedTable);

                            $relation = $instance->hasOne(DynamicRecord::class, $foreignKey, $localKey);
                            $relation->getRelated()->setTable($relatedTable);
                            $relation->getQuery()->from($relatedTable);
                            return $relation;
                        }
                        return null;
                     });
                     $query->with($uniqueRelKey);
                }
            }
        }

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

        foreach ($model->fields->where('is_filterable', true) as $field) {
            if ($request->has($field->name) && $request->input($field->name) !== null) {
                $query->where($field->name, $request->input($field->name));
            }
        }

        $sortField = $request->get('sort', 'id');
        $sortDirection = $request->get('direction', 'desc');
        $sortableFields = $model->fields->where('is_sortable', true)->pluck('name')->toArray();
        $sortableFields[] = 'id';
        $sortableFields[] = 'created_at';
        $sortableFields[] = 'updated_at';

        if (in_array($sortField, $sortableFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min($request->get('per_page', 15), 100);
        $data = $query->paginate($perPage);

        $hiddenFields = $model->fields->where('is_hidden', true)->pluck('name')->toArray();
        $data->getCollection()->each(function ($item) use ($hiddenFields) {
            if (method_exists($item, 'makeHidden')) {
                $item->makeHidden($hiddenFields);
            }
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

        $tableName = $model->table_name;

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $query = (new DynamicRecord)->setDynamicTable($tableName)->newQuery();

        if ($model->has_soft_deletes) {
            $query->whereNull('deleted_at');
        }

        if ($request->has('include')) {
            $includes = explode(',', $request->get('include'));
            foreach ($includes as $include) {
                $relationName = trim($include);
                $relDef = $model->relationships()->where('name', $relationName)->first();

                if ($relDef && $relDef->relatedModel) {
                     // Namespace relation key with table name to prevent global
                     // state collisions in Octane/Swoole (Bug #4 fix)
                     $uniqueRelKey = $tableName . '__' . $relationName;

                     DynamicRecord::resolveRelationUsing($uniqueRelKey, function ($instance) use ($relDef) {
                        $relatedTable = $relDef->relatedModel->table_name;
                        $foreignKey = $relDef->foreign_key;
                        $localKey = $relDef->local_key ?? 'id';
                        $qualify = fn($col, $table) => str_contains($col, '.') ? $col : "$table.$col";

                        if ($relDef->type === 'hasMany') {
                            $foreignKey = $foreignKey ?: Str::singular($instance->getTable()) . '_id';
                            $foreignKey = $qualify($foreignKey, $relatedTable);

                            $relation = $instance->hasMany(DynamicRecord::class, $foreignKey, $localKey);
                            $relation->getRelated()->setTable($relatedTable);
                            $relation->getQuery()->from($relatedTable);
                            return $relation;
                        } elseif ($relDef->type === 'belongsTo') {
                            $foreignKey = $foreignKey ?: Str::singular($relDef->relatedModel->table_name) . '_id';

                            $relation = $instance->belongsTo(DynamicRecord::class, $foreignKey, $localKey, $relDef->name);
                            $relation->getRelated()->setTable($relatedTable);
                            $relation->getQuery()->from($relatedTable);
                            return $relation;
                        } elseif ($relDef->type === 'hasOne') {
                            $foreignKey = $foreignKey ?: Str::singular($instance->getTable()) . '_id';
                            $foreignKey = $qualify($foreignKey, $relatedTable);

                            $relation = $instance->hasOne(DynamicRecord::class, $foreignKey, $localKey);
                            $relation->getRelated()->setTable($relatedTable);
                            $relation->getQuery()->from($relatedTable);
                            return $relation;
                        }
                     });
                     $query->with($uniqueRelKey);
                }
            }
        }

        $record = $query->find($id);

        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        if (!$this->validateRule($model->view_rule, $record)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }

        $hiddenFields = $model->fields->where('is_hidden', true)->pluck('name')->toArray();
        $record->makeHidden($hiddenFields);

        return response()->json(['data' => $record]);
    }

    /**
     * Create a new record in a dynamic model.
     * 🔒 TRANSACTION WRAPPER: Atomic operation
     * 🎯 TYPE-SAFE CASTING: Strict type enforcement
     * 📁 FILE UPLOAD SUPPORT: Spatie Media Library integration
     */
    public function store(Request $request, string $tableName): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        if (!$this->validateRule($model->create_rule)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }
        
        $tableName = $model->table_name;

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $rules = $this->buildValidationRules($model);
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $record = $this->executeInTransaction(function () use ($request, $model, $tableName) {
                $data = [];
                $fileFields = [];
                
                foreach ($model->fields as $field) {
                    // Skip file/image fields - handle them separately
                    if (in_array($field->type, ['file', 'image'])) {
                        if ($request->hasFile($field->name)) {
                            $fileFields[] = $field;
                        }
                        continue;
                    }
                    
                    if ($request->has($field->name)) {
                        $data[$field->name] = $this->castValue($request->input($field->name), $field);
                    } elseif ($field->default_value !== null) {
                        $data[$field->name] = $field->default_value;
                    }
                }

                if ($model->has_timestamps) {
                    $data['created_at'] = now();
                    $data['updated_at'] = now();
                }

                $record = new DynamicRecord();
                $record->setDynamicTable($tableName);
                $record->timestamps = false;
                $record->fill($data);
                $record->save();
                
                // 📸 Handle File Uploads using Spatie Media Library
                $fileFields = $model->fields->whereIn('type', ['image', 'file']);
                foreach ($fileFields as $field) {
                    if ($request->hasFile($field->name)) {
                        $record->addMediaFromRequest($field->name)
                               ->toMediaCollection($field->type === 'image' ? 'images' : 'files', 'digibase_storage');
                    }
                }
                
                return $record;
            });

            event(new ModelActivity('created', $model->name, $record, $request->user()));

            $this->triggerWebhooks($model->id, 'created', [
                'table' => $tableName,
                'record' => $record->toArray(),
            ]);

            // Include media URLs in response
            $responseData = $record->toArray();
            if (method_exists($record, 'getMedia')) {
                $responseData['media'] = [
                    'files' => $record->getMedia('files')->map(function($media) {
                        try {
                            return [
                                'id' => $media->id,
                                'name' => $media->name,
                                'file_name' => $media->file_name,
                                'mime_type' => $media->mime_type,
                                'size' => $media->size,
                                'url' => $media->getUrl(),
                            ];
                        } catch (\Throwable) {
                            // Fallback to direct storage URL if model reload fails (common in dynamic tables)
                            return [
                                'id' => $media->id,
                                'name' => $media->name,
                                'file_name' => $media->file_name,
                                'mime_type' => $media->mime_type,
                                'size' => $media->size,
                                'url' => Storage::disk($media->disk ?? 'digibase_storage')->url($media->id . '/' . $media->file_name),
                            ];
                        }
                    }),
                    'images' => $record->getMedia('images')->map(function($media) {
                        try {
                            return [
                                'id' => $media->id,
                                'name' => $media->name,
                                'file_name' => $media->file_name,
                                'mime_type' => $media->mime_type,
                                'size' => $media->size,
                                'url' => $media->getUrl(),
                                'thumb_url' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : null,
                                'preview_url' => $media->hasGeneratedConversion('preview') ? $media->getUrl('preview') : null,
                            ];
                        } catch (\Exception) {
                             return [
                                'id' => $media->id,
                                'name' => $media->name,
                                'file_name' => $media->file_name,
                                'mime_type' => $media->mime_type,
                                'size' => $media->size,
                                'url' => Storage::disk($media->disk ?? 'digibase_storage')->url($media->id . '/' . $media->file_name),
                                'thumb_url' => null,
                                'preview_url' => null,
                            ];
                        }
                    }),
                ];
            }

            return response()->json(['data' => $responseData], 201);
        } catch (\Exception $e) {
            Log::error('Record creation failed', [
                'table' => $tableName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Failed to create record',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update a record in a dynamic model.
     * 🔒 TRANSACTION WRAPPER: Atomic operation
     * 🎯 TYPE-SAFE CASTING: Strict type enforcement
     * 📁 FILE UPLOAD SUPPORT: Spatie Media Library integration
     */
    public function update(Request $request, string $tableName, int $id): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }
        
        $tableName = $model->table_name;

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $record = DB::table($tableName)->where('id', $id)->first();

        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        if (!$this->validateRule($model->update_rule, $record)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }

        $rules = $this->buildValidationRules($model, true, $id);
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $updatedRecord = $this->executeInTransaction(function () use ($request, $model, $tableName, $id) {
                $data = [];
                $fileFields = [];
                
                foreach ($model->fields as $field) {
                    // Skip file/image fields - handle them separately
                    if (in_array($field->type, ['file', 'image'])) {
                        if ($request->hasFile($field->name)) {
                            $fileFields[] = $field;
                        }
                        continue;
                    }
                    
                    if ($request->has($field->name)) {
                        $data[$field->name] = $this->castValue($request->input($field->name), $field);
                    }
                }

                if ($model->has_timestamps) {
                    $data['updated_at'] = now();
                }

                $recordInstance = new DynamicRecord();
                $recordInstance->setDynamicTable($tableName);
                
                $pdoRecord = $recordInstance->findOrFail($id);
                $pdoRecord->setDynamicTable($tableName);
                $pdoRecord->timestamps = false;
                
                if (!empty($data)) {
                    $pdoRecord->update($data);
                }

                // 📸 Handle File Uploads using Spatie Media Library
                $fileFields = $model->fields->whereIn('type', ['image', 'file']);
                foreach ($fileFields as $field) {
                    if ($request->hasFile($field->name)) {
                        // Clear existing media if replacing
                        if ($request->input('replace_' . $field->name, false)) {
                            $pdoRecord->clearMediaCollection($field->type === 'image' ? 'images' : 'files');
                        }
                        
                        $pdoRecord->addMediaFromRequest($field->name)
                                  ->toMediaCollection($field->type === 'image' ? 'images' : 'files', 'digibase_storage');
                    }
                }

                return $pdoRecord;
            });

            event(new ModelActivity('updated', $model->name, $updatedRecord, $request->user()));

            $this->triggerWebhooks($model->id, 'updated', [
                'table' => $tableName,
                'record' => $updatedRecord->toArray(),
            ]);

            // Include media URLs in response
            $responseData = $updatedRecord->toArray();
            if (method_exists($updatedRecord, 'getMedia')) {
                $responseData['media'] = [
                    'files' => $updatedRecord->getMedia('files')->map(fn($media) => [
                        'id' => $media->id,
                        'name' => $media->name,
                        'file_name' => $media->file_name,
                        'mime_type' => $media->mime_type,
                        'size' => $media->size,
                        'url' => $media->getUrl(),
                    ]),
                    'images' => $updatedRecord->getMedia('images')->map(fn($media) => [
                        'id' => $media->id,
                        'name' => $media->name,
                        'file_name' => $media->file_name,
                        'mime_type' => $media->mime_type,
                        'size' => $media->size,
                        'url' => $media->getUrl(),
                        'thumb_url' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : null,
                        'preview_url' => $media->hasGeneratedConversion('preview') ? $media->getUrl('preview') : null,
                    ]),
                ];
            }

            return response()->json(['data' => $responseData]);
        } catch (\Exception $e) {
            Log::error('Record update failed', [
                'table' => $tableName,
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Failed to update record',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete a record from a dynamic model.
     * 🔒 TRANSACTION WRAPPER: Atomic operation
     */
    public function destroy(Request $request, string $tableName, int $id): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }
        
        $tableName = $model->table_name;

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $record = DB::table($tableName)->where('id', $id)->first();

        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        if (!$this->validateRule($model->delete_rule, $record)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }

        $recordData = (array) $record;

        try {
            $this->executeInTransaction(function () use ($model, $tableName, $id) {
                $recordInstance = new DynamicRecord();
                $recordInstance->setDynamicTable($tableName);
                
                $pdoRecord = $recordInstance->findOrFail($id);
                $pdoRecord->setDynamicTable($tableName);
                $pdoRecord->timestamps = false;

                if ($model->has_soft_deletes) {
                    $softDeleteData = ['deleted_at' => now()];
                    if ($model->has_timestamps) {
                        $softDeleteData['updated_at'] = now();
                    }
                    $pdoRecord->update($softDeleteData);
                } else {
                    $pdoRecord->delete();
                }
            });

            event(new ModelActivity('deleted', $model->name, ['id' => $id], $request->user()));

            $this->triggerWebhooks($model->id, 'deleted', [
                'table' => $tableName,
                'record' => $recordData,
            ]);

            return response()->json(['message' => 'Record deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Record deletion failed', [
                'table' => $tableName,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => 'Failed to delete record',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
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
                    'validation_rules' => $field->validation_rules,
                    'is_hidden' => $field->is_hidden ?? false,
                ];
            }),
            'endpoints' => [
                'list' => [
                    'method' => 'GET',
                    'url' => "/api/v1/data/{$tableName}",
                    'description' => 'List all records with pagination, search, and filters',
                ],
                'create' => [
                    'method' => 'POST',
                    'url' => "/api/v1/data/{$tableName}",
                    'description' => 'Create a new record',
                ],
                'show' => [
                    'method' => 'GET',
                    'url' => "/api/v1/data/{$tableName}/{id}",
                    'description' => 'Get a single record by ID',
                ],
                'update' => [
                    'method' => 'PUT',
                    'url' => "/api/v1/data/{$tableName}/{id}",
                    'description' => 'Update a record by ID',
                ],
                'delete' => [
                    'method' => 'DELETE',
                    'url' => "/api/v1/data/{$tableName}/{id}",
                    'description' => 'Delete a record by ID',
                ],
            ],
        ]);
    }
}
