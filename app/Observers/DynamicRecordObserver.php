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
 */
class DynamicRecordObserver
{
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
     */
    protected function filterHiddenFields(string $tableName, array $data): array
    {
        $model = DynamicModel::where('table_name', $tableName)
            ->with('fields')
            ->first();

        if (!$model) {
            return $data;
        }

        $hiddenFields = $model->fields
            ->where('is_hidden', true)
            ->pluck('name')
            ->toArray();

        // Also always strip common sensitive patterns
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
     * Clear cache for a specific table using the dedicated digibase store.
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
    }
}
