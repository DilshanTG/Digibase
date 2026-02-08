<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'dynamic_model_id',
        'name',
        'url',
        'secret',
        'events',
        'headers',
        'is_active',
        'last_triggered_at',
        'failure_count',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'headers' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    /**
     * Get the dynamic model this webhook belongs to.
     */
    public function dynamicModel(): BelongsTo
    {
        return $this->belongsTo(DynamicModel::class);
    }

    /**
     * Check if this webhook should trigger for a given event.
     */
    public function shouldTrigger(string $event): bool
    {
        return $this->is_active && in_array($event, $this->events ?? []);
    }

    /**
     * Generate HMAC signature for payload verification.
     */
    public function generateSignature(array $payload): ?string
    {
        if (empty($this->secret)) {
            return null;
        }

        return hash_hmac('sha256', json_encode($payload), $this->secret);
    }

    /**
     * Record a successful trigger.
     */
    public function recordSuccess(): void
    {
        $this->update([
            'last_triggered_at' => now(),
            'failure_count' => 0,
        ]);
    }

    /**
     * Record a failed trigger.
     */
    public function recordFailure(): void
    {
        $this->increment('failure_count');
        
        // Auto-disable after 10 consecutive failures
        if ($this->failure_count >= 10) {
            $this->update(['is_active' => false]);
        }
    }
}
