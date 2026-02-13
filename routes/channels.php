<?php

use App\Models\ApiKey;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Private data channel for real-time model updates.
 *
 * Channel: private-data.{tableName}
 * Authorization: User must own at least one active API key with read scope
 * and access to the requested table.
 */
Broadcast::channel('data.{tableName}', function ($user, $tableName) {
    // User must have at least one active, non-expired key with read access to this table
    $keys = ApiKey::where('user_id', $user->id)
        ->where('is_active', true)
        ->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        })
        ->get();

    foreach ($keys as $key) {
        if ($key->hasPermission('read') && $key->hasTableAccess($tableName)) {
            return true;
        }
    }

    return false;
});
