<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time model change event.
 *
 * Broadcasts on a private channel so only authenticated users
 * with valid API key access can receive updates.
 *
 * Channel: private-data.{table}
 * Event name: model.changed
 */
class ModelChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $table The table/model name
     * @param string $action The action: 'created', 'updated', 'deleted'
     * @param array|null $data The record data (already filtered for hidden fields)
     */
    public function __construct(
        public string $table,
        public string $action,
        public ?array $data = null
    ) {}

    /**
     * Broadcast on a private channel so authorization is enforced.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("data.{$this->table}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'model.changed';
    }

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
