<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 📡 LIVE WIRE: Real-time model change event
 * 
 * Broadcasts immediately when data changes via the API.
 * Frontend clients can subscribe to `public-data.{table}` channel.
 */
class ModelChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $table The table/model name
     * @param string $action The action: 'created', 'updated', 'deleted'
     * @param array|null $data The record data (null for deletes with just ID)
     */
    public function __construct(
        public string $table,
        public string $action,
        public ?array $data = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     * Uses public channel for broad accessibility.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("public-data.{$this->table}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'model.changed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'table' => $this->table,
            'action' => $this->action,
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
