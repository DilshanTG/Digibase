<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Events\ModelChanged;

class DynamicRecord extends Model
{
    protected $guarded = [];

    /**
     * Allow setting the table name dynamically at runtime.
     * 
     * @param string $tableName
     * @return $this
     */
    public function setDynamicTable(string $tableName): self
    {
        $this->setTable($tableName);
        return $this;
    }

    /**
     * 📡 LIVE WIRE: The "Heartbeat" of the Real-Time System
     * 
     * This fires events whenever data is saved or deleted,
     * regardless of whether it came from:
     * - API Controller
     * - Filament Admin Panel
     * - Tinker/CLI
     * - Queue Jobs
     */
    protected static function booted()
    {
        // 🔥 On Create or Update
        static::saved(function ($record) {
            // Only fire if we're in a "Dynamic" context with a table name
            if ($record->getTable()) {
                $action = $record->wasRecentlyCreated ? 'created' : 'updated';
                
                // Dispatch the real-time event
                event(new ModelChanged(
                    $record->getTable(), 
                    $action, 
                    $record->toArray()
                ));
            }
        });

        // 🗑️ On Delete
        static::deleted(function ($record) {
            if ($record->getTable()) {
                event(new ModelChanged(
                    $record->getTable(), 
                    'deleted', 
                    ['id' => $record->id]
                ));
            }
        });
    }
}
