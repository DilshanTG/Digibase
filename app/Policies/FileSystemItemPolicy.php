<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use MWGuerra\FileManager\Models\FileSystemItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class FileSystemItemPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FileSystemItem');
    }

    public function view(AuthUser $authUser, FileSystemItem $fileSystemItem): bool
    {
        return $authUser->can('View:FileSystemItem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FileSystemItem');
    }

    public function update(AuthUser $authUser, FileSystemItem $fileSystemItem): bool
    {
        return $authUser->can('Update:FileSystemItem');
    }

    public function delete(AuthUser $authUser, FileSystemItem $fileSystemItem): bool
    {
        return $authUser->can('Delete:FileSystemItem');
    }

    public function restore(AuthUser $authUser, FileSystemItem $fileSystemItem): bool
    {
        return $authUser->can('Restore:FileSystemItem');
    }

    public function forceDelete(AuthUser $authUser, FileSystemItem $fileSystemItem): bool
    {
        return $authUser->can('ForceDelete:FileSystemItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FileSystemItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FileSystemItem');
    }

    public function replicate(AuthUser $authUser, FileSystemItem $fileSystemItem): bool
    {
        return $authUser->can('Replicate:FileSystemItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FileSystemItem');
    }

}