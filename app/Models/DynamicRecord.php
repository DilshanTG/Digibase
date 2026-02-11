<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * DynamicRecord: A flexible model for dynamic tables.
 * 
 * Now supports Spatie Media Library for professional file handling.
 * Event broadcasting and cache clearing are handled by
 * DynamicRecordObserver (registered in AppServiceProvider).
 */
class DynamicRecord extends Model implements HasMedia
{
    use InteractsWithMedia;
    use LogsActivity;

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
     * Override getMorphClass to support dynamic tables in Spatie Media Library.
     */
    public function getMorphClass()
    {
        return $this->getTable();
    }

    /**
     * Register media collections for this model.
     * Supports multiple file types with automatic optimization.
     */
    public function registerMediaCollections(): void
    {
        // General files collection
        $this->addMediaCollection('files')
            ->useDisk('digibase_storage')
            ->acceptsMimeTypes([
                // Images
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                // Documents
                'application/pdf', 'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain', 'text/csv',
                // Archives
                'application/zip', 'application/x-rar-compressed',
                // Media
                'video/mp4', 'video/webm', 'audio/mpeg', 'audio/wav',
            ]);

        // Images collection with automatic optimization
        $this->addMediaCollection('images')
            ->useDisk('digibase_storage')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->registerMediaConversions(function () {
                $this->addMediaConversion('thumb')
                    ->width(150)
                    ->height(150)
                    ->sharpen(10)
                    ->nonQueued();

                $this->addMediaConversion('preview')
                    ->width(800)
                    ->height(600)
                    ->sharpen(10)
                    ->nonQueued();
            });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getTable())
            ->logAll();
    }
    /**
     * Override toArray to strict snake_case keys globally.
     * Also strips internal table prefixes from joined relations.
     */
    public function toArray()
    {
        $attributes = parent::toArray();
        $cleaned = [];
        $prefix = $this->getTable() . '__';

        foreach ($attributes as $key => $value) {
            // 1. Strip Prefix (e.g. mobile_phones__Phone Name -> Phone Name)
            if (is_string($key) && str_starts_with($key, $prefix)) {
                $key = substr($key, strlen($prefix));
            }
            
            // 2. Snake Case Conversion (e.g. Phone Name -> phone_name)
            $cleaned[\Illuminate\Support\Str::snake($key)] = $value;
        }

        return $cleaned;
    }
}
