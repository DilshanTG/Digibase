<?php

namespace App\Observers;

use App\Models\DynamicModel;
use App\Models\DynamicRelationship;
use Illuminate\Support\Facades\Schema;

class DynamicModelObserver
{
    /**
     * 👻 ORPHAN CLEANUP: Handle the DynamicModel "deleting" event.
     * Cascade delete all relationships to prevent orphaned references.
     */
    public function deleting(DynamicModel $dynamicModel): void
    {
        // 1. Delete all relationships pointing TO this model
        DynamicRelationship::where('related_model_id', $dynamicModel->id)->delete();

        // 2. Delete all relationships defined BY this model
        DynamicRelationship::where('dynamic_model_id', $dynamicModel->id)->delete();

        // 3. Drop the physical table
        if (Schema::hasTable($dynamicModel->table_name)) {
            Schema::dropIfExists($dynamicModel->table_name);
        }
    }

    /**
     * Handle the DynamicModel "created" event.
     */
    public function created(DynamicModel $dynamicModel): void
    {
        //
    }

    /**
     * Handle the DynamicModel "updated" event.
     */
    public function updated(DynamicModel $dynamicModel): void
    {
        //
    }

    /**
     * Handle the DynamicModel "deleted" event.
     */
    public function deleted(DynamicModel $dynamicModel): void
    {
        //
    }

    /**
     * Handle the DynamicModel "restored" event.
     */
    public function restored(DynamicModel $dynamicModel): void
    {
        //
    }

    /**
     * Handle the DynamicModel "force deleted" event.
     */
    public function forceDeleted(DynamicModel $dynamicModel): void
    {
        //
    }
}
