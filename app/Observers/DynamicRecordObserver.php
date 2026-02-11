<?php

namespace App\Observers;

use App\Models\DynamicModel;
use App\Models\DynamicRecord;
use App\Events\ModelChanged;
use Illuminate\Support\Facades\Cache;

/**
 * Central nervous system for DynamicRecord lifecycle.
 *
 * Handles:
 * - Real-time event broadcasting (with hidden field filtering)
 * - Cache invalidation (using dedicated digibase store)
 *
 * Performance: Hidden field lookups are cached in-process (static array)
 * AND in the digibase cache store (60s TTL) to eliminate N+1 queries
 * during bulk operations.
 */
class DynamicRecordObserver
{
    /**
     * In-process static cache — survives across multiple observer calls
     * within the same request/bulk operation. Zero database queries after
     * the first call for a given table.
     */
    protected static array $hiddenFieldsCache = [];

    public function created(DynamicRecord $record): void
    {
        $tableName = $record->getTable();

        if ($tableName) {
            $data = $this->filterHiddenFields($tableName, $record->toArray());
            event(new ModelChanged($tableName, 'created', $data));
            $this->clearTableCache($tableName);
        }
    }

    public function updated(DynamicRecord $record): void
    {
        $tableName = $record->getTable();

        if ($tableName) {
            // Detect soft delete
            if ($record->wasChanged('deleted_at') && !empty($record->getAttribute('deleted_at'))) {
                event(new ModelChanged($tableName, 'deleted', ['id' => $record->id]));
                $this->clearTableCache($tableName);
                return;
            }

            $data = $this->filterHiddenFields($tableName, $record->toArray());
            event(new ModelChanged($tableName, 'updated', $data));
            $this->clearTableCache($tableName);
        }
    }

    public function deleted(DynamicRecord $record): void
    {
        $tableName = $record->getTable();

        if ($tableName) {
            event(new ModelChanged($tableName, 'deleted', ['id' => $record->id]));
            $this->clearTableCache($tableName);
        }
    }

    /**
     * Remove fields marked as is_hidden in the DynamicModel schema
     * before broadcasting. Prevents leaking passwords, tokens, etc.
     *
     * Uses a two-tier cache:
     * 1. Static in-process array (free, survives the entire request)
     * 2. Digibase cache store with 60s TTL (survives across requests)
     */
    protected function filterHiddenFields(string $tableName, array $data): array
    {
        $hiddenFields = $this->getHiddenFields($tableName);
        $sensitivePatterns = ['password', 'secret', 'token', 'key', 'credential'];

        foreach ($data as $key => $value) {
            if (in_array($key, $hiddenFields)) {
                unset($data[$key]);
                continue;
            }

            foreach ($sensitivePatterns as $pattern) {
                if (stripos($key, $pattern) !== false) {
                    unset($data[$key]);
                    break;
                }
            }
        }

        return $data;
    }

    /**
     * Get hidden field names for a table, with two-tier caching.
     *
     * Tier 1: Static array — zero-cost for bulk operations within one request.
     * Tier 2: Digibase cache store (60s TTL) — avoids DB hit across requests.
     * Tier 3: Database query — only on cold cache.
     */
    protected function getHiddenFields(string $tableName): array
    {
        // Tier 1: In-process static cache
        if (isset(static::$hiddenFieldsCache[$tableName])) {
            return static::$hiddenFieldsCache[$tableName];
        }

        // Tier 2: Digibase cache store
        $cacheKey = "observer:hidden_fields:{$tableName}";

        try {
            $fields = Cache::store('digibase')->remember($cacheKey, 60, function () use ($tableName) {
                // Tier 3: Database query (only runs on cold cache)
                $model = DynamicModel::where('table_name', $tableName)
                    ->with('fields')
                    ->first();

                if (!$model) {
                    return [];
                }

                return $model->fields
                    ->where('is_hidden', true)
                    ->pluck('name')
                    ->map(fn($name) => \Illuminate\Support\Str::snake($name))
                    ->toArray();
            });
        } catch (\Throwable) {
            $fields = [];
        }

        // Store in static cache for the rest of this request
        static::$hiddenFieldsCache[$tableName] = $fields;

        return $fields;
    }

    /**
     * Clear cache for a specific table using the dedicated digibase store.
     * Also clears the observer's hidden fields cache for immediate effect.
     */
    protected function clearTableCache(string $tableName): void
    {
        $store = Cache::store('digibase');
        $driver = config('cache.stores.digibase.driver', 'file');

        if (in_array($driver, ['redis', 'memcached', 'dynamodb'])) {
            $store->tags(["digibase:{$tableName}"])->flush();
        } else {
            // For file/database: flush the entire dedicated digibase store.
            // This is safe because it only contains API cache, not sessions/routes.
            $store->flush();
        }

        // Also clear the observer's cached hidden fields for this table
        // so schema changes take effect immediately
        unset(static::$hiddenFieldsCache[$tableName]);
        $store->forget("observer:hidden_fields:{$tableName}");
    }
}
