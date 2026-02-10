<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DynamicModel;
use Illuminate\Auth\Access\HandlesAuthorization;

class DynamicModelPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DynamicModel');
    }

    public function view(AuthUser $authUser, DynamicModel $dynamicModel): bool
    {
        return $authUser->can('View:DynamicModel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DynamicModel');
    }

    public function update(AuthUser $authUser, DynamicModel $dynamicModel): bool
    {
        return $authUser->can('Update:DynamicModel');
    }

    public function delete(AuthUser $authUser, DynamicModel $dynamicModel): bool
    {
        return $authUser->can('Delete:DynamicModel');
    }

    public function restore(AuthUser $authUser, DynamicModel $dynamicModel): bool
    {
        return $authUser->can('Restore:DynamicModel');
    }

    public function forceDelete(AuthUser $authUser, DynamicModel $dynamicModel): bool
    {
        return $authUser->can('ForceDelete:DynamicModel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DynamicModel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DynamicModel');
    }

    public function replicate(AuthUser $authUser, DynamicModel $dynamicModel): bool
    {
        return $authUser->can('Replicate:DynamicModel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DynamicModel');
    }

}