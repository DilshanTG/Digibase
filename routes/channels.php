<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * 📡 LIVE WIRE: Public data channel for real-time model updates.
 * 
 * This is a public channel that allows any connected client to receive
 * updates for a specific table. Authentication is handled at the API
 * level via API keys, not at the WebSocket level.
 * 
 * Channel: public-data.{tableName}
 * Events: model.changed (created, updated, deleted)
 */
// Note: Public channels don't need authorization callbacks.
// The Channel class (not PrivateChannel) is used in ModelChanged event.
