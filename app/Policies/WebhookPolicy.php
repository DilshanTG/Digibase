<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Webhook;
use Illuminate\Auth\Access\HandlesAuthorization;

class WebhookPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Webhook');
    }

    public function view(AuthUser $authUser, Webhook $webhook): bool
    {
        return $authUser->can('View:Webhook');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Webhook');
    }

    public function update(AuthUser $authUser, Webhook $webhook): bool
    {
        return $authUser->can('Update:Webhook');
    }

    public function delete(AuthUser $authUser, Webhook $webhook): bool
    {
        return $authUser->can('Delete:Webhook');
    }

    public function restore(AuthUser $authUser, Webhook $webhook): bool
    {
        return $authUser->can('Restore:Webhook');
    }

    public function forceDelete(AuthUser $authUser, Webhook $webhook): bool
    {
        return $authUser->can('ForceDelete:Webhook');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Webhook');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Webhook');
    }

    public function replicate(AuthUser $authUser, Webhook $webhook): bool
    {
        return $authUser->can('Replicate:Webhook');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Webhook');
    }

}