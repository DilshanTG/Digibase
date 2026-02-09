<?php

namespace App\Observers;

use App\Models\DynamicRecord;
use App\Events\ModelChanged;
use Illuminate\Support\Facades\Cache;

/**
 * 🧠 CENTRAL NERVOUS SYSTEM: DynamicRecordObserver
 * 
 * This Observer is the single source of truth for:
 * - 📡 Real-time event broadcasting (Live Wire)
 * - ⚡ Cache invalidation (Turbo Cache)
 * 
 * This ensures consistent behavior regardless of how data is modified:
 * - API Controller
 * - Filament Admin Panel
 * - Tinker/CLI
 * - Queue Jobs
 * - Database Seeders
 */
class DynamicRecordObserver
{
    /**
     * Handle the DynamicRecord "created" event.
     */
    public function created(DynamicRecord $record): void
    {
        $tableName = $record->getTable();
        
        if ($tableName) {
            // 📡 LIVE WIRE: Broadcast to connected clients
            event(new ModelChanged($tableName, 'created', $record->toArray()));
            
            // ⚡ TURBO CACHE: Clear stale cache
            $this->clearTableCache($tableName);
        }
    }

    /**
     * Handle the DynamicRecord "updated" event.
     */
    public function updated(DynamicRecord $record): void
    {
        $tableName = $record->getTable();
        
        if ($tableName) {
            // DETECT SOFT DELETE: If deleted_at was set, treat as deleted
            if ($record->wasChanged('deleted_at') && !empty($record->getAttribute('deleted_at'))) {
                event(new ModelChanged($tableName, 'deleted', ['id' => $record->id]));
                $this->clearTableCache($tableName);
                return;
            }

            // 📡 LIVE WIRE: Broadcast to connected clients
            event(new ModelChanged($tableName, 'updated', $record->toArray()));
            
            // ⚡ TURBO CACHE: Clear stale cache
            $this->clearTableCache($tableName);
        }
    }

    /**
     * Handle the DynamicRecord "deleted" event.
     */
    public function deleted(DynamicRecord $record): void
    {
        $tableName = $record->getTable();
        
        if ($tableName) {
            // 📡 LIVE WIRE: Broadcast to connected clients
            event(new ModelChanged($tableName, 'deleted', ['id' => $record->id]));
            
            // ⚡ TURBO CACHE: Clear stale cache
            $this->clearTableCache($tableName);
        }
    }

    /**
     * Clear cache for a specific table.
     * Supports both tagged cache (Redis) and file cache.
     */
    protected function clearTableCache(string $tableName): void
    {
        $driver = config('cache.default');
        
        // Tagged cache for Redis/Memcached
        if (in_array($driver, ['redis', 'memcached', 'dynamodb'])) {
            Cache::tags(["digibase:{$tableName}"])->flush();
        } else {
            // For file/database cache, we need pattern-based clearing
            // Best effort: clear all Digibase cache
            // In production, use Redis for proper tagging support
            Cache::flush();
        }
    }
}
