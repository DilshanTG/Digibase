<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ModelActivity implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public string $type;
    public string $modelName;
    public $data;
    public $user;

    /**
     * Create a new event instance.
     */
    public function __construct(string $type, string $modelName, $data, $user = null)
    {
        $this->type = $type;
        $this->modelName = $modelName;
        $this->data = $data;
        $this->user = $user;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('digibase.activity'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ModelActivity';
    }
}
