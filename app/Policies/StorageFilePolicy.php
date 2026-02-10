<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\StorageFile;
use Illuminate\Auth\Access\HandlesAuthorization;

class StorageFilePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StorageFile');
    }

    public function view(AuthUser $authUser, StorageFile $storageFile): bool
    {
        return $authUser->can('View:StorageFile');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StorageFile');
    }

    public function update(AuthUser $authUser, StorageFile $storageFile): bool
    {
        return $authUser->can('Update:StorageFile');
    }

    public function delete(AuthUser $authUser, StorageFile $storageFile): bool
    {
        return $authUser->can('Delete:StorageFile');
    }

    public function restore(AuthUser $authUser, StorageFile $storageFile): bool
    {
        return $authUser->can('Restore:StorageFile');
    }

    public function forceDelete(AuthUser $authUser, StorageFile $storageFile): bool
    {
        return $authUser->can('ForceDelete:StorageFile');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StorageFile');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StorageFile');
    }

    public function replicate(AuthUser $authUser, StorageFile $storageFile): bool
    {
        return $authUser->can('Replicate:StorageFile');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StorageFile');
    }

}