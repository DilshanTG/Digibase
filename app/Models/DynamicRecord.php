<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
}
