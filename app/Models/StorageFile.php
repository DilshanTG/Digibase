<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StorageFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'bucket',
        'folder',
        'is_public',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_public' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the full URL to the file.
     */
    public function getUrlAttribute(): string
    {
        if ($this->is_public) {
            return '/storage/' . $this->path;
        }

        return '/api/storage/' . $this->id . '/download';
    }

    /**
     * Get human-readable file size.
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get file extension.
     */
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->original_name, PATHINFO_EXTENSION);
    }

    /**
     * Check if file is an image.
     */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
