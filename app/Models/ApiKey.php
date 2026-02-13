<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'key',
        'key_hash',         // SHA-256 of key for indexed O(1) lookup
        'type',             // 'public' or 'secret'
        'scopes',           // JSON array: ['read', 'write', 'delete']
        'permissions',      // JSON array: ['read', 'create', 'update', 'delete']
        'allowed_tables',   // JSON array: ['posts', 'comments'] or null/empty for all
        'rate_limit',       // Requests per minute
        'is_active',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'permissions' => 'array',
        'allowed_tables' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the table access mode based on allowed_tables.
     * Returns 'all' if allowed_tables is empty, 'selected' otherwise.
     */
    public function getTableAccessModeAttribute(): string
    {
        return empty($this->allowed_tables) ? 'all' : 'selected';
    }

    /**
     * Set the table access mode. Clears allowed_tables when set to 'all'.
     */
    public function setTableAccessModeAttribute(string $value): void
    {
        if ($value === 'all') {
            $this->allowed_tables = null;
        }
        // If 'selected', allowed_tables should be set separately
    }

    protected $hidden = [
        'key', // Never expose the full key in responses
    ];

    protected static function booted(): void
    {
        static::saving(function (ApiKey $apiKey) {
            // Auto-compute key_hash whenever the key is set or changed
            if ($apiKey->isDirty('key') && $apiKey->key) {
                $apiKey->key_hash = hash('sha256', $apiKey->key);
            }
        });
    }

    /**
     * Find an API key by its plain-text token using indexed hash lookup.
     * Returns null if not found or inactive.
     */
    public static function findByToken(string $token): ?self
    {
        $hash = hash('sha256', $token);

        $apiKey = static::where('key_hash', $hash)
            ->where('is_active', true)
            ->first();

        // Final constant-time verification to prevent any hash collision attack
        if ($apiKey && hash_equals($apiKey->key, $token)) {
            return $apiKey;
        }

        return null;
    }

    /**
     * The user who owns this API key.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new API key.
     *
     * @param  string  $type  'public' or 'secret'
     * @return string The plain text key (only shown once!)
     */
    public static function generateKey(string $type = 'public'): string
    {
        // 🎯 Fix 19: Digibase Prefixing
        $prefix = $type === 'secret' ? 'dg_sk_' : 'dg_pk_';

        return $prefix.Str::random(40);
    }

    /**
     * Check if key has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        // Empty/null permissions means unrestricted access
        if (empty($permissions)) {
            return true;
        }

        return in_array('*', $permissions) || in_array($permission, $permissions);
    }

    /**
     * Check if key can read data.
     */
    public function canRead(): bool
    {
        return $this->hasPermission('read');
    }

    /**
     * Check if key can create data.
     */
    public function canCreate(): bool
    {
        return $this->hasPermission('create');
    }

    /**
     * Check if key can update data.
     */
    public function canUpdate(): bool
    {
        return $this->hasPermission('update');
    }

    /**
     * Check if key can delete data.
     */
    public function canDelete(): bool
    {
        return $this->hasPermission('delete');
    }

    /**
     * Check if key has access to a specific table.
     * Null/empty allowed_tables means access to all tables.
     */
    public function hasTableAccess(string $tableName): bool
    {
        $allowed = $this->allowed_tables;

        // Null or empty array = unrestricted access to all tables
        if (empty($allowed)) {
            return true;
        }

        return in_array($tableName, $allowed);
    }

    /**
     * Check if the key is valid (active and not expired).
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Record that this key was used.
     */
    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Get masked key for display (pk_xxxx...xxxx).
     */
    public function getMaskedKeyAttribute(): string
    {
        $key = $this->key;
        if (strlen($key) < 10) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 6).'...'.substr($key, -4);
    }

    /**
     * Scope: Active keys only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Non-expired keys only.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
