<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'table_name',
        'display_name',
        'description',
        'icon',
        'is_active',
        'has_timestamps',
        'has_soft_deletes',
        'generate_api',
        'settings',
        // RLS Rules
        'list_rule',
        'view_rule',
        'create_rule',
        'update_rule',
        'delete_rule',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'has_timestamps' => 'boolean',
            'has_soft_deletes' => 'boolean',
            'generate_api' => 'boolean',
            'settings' => 'array',
            'is_syncing' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DynamicField::class)->orderBy('order');
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(DynamicRelationship::class);
    }

    public function relatedTo(): HasMany
    {
        return $this->hasMany(DynamicRelationship::class, 'related_model_id');
    }

    /**
     * 🎯 Fix 2: Global Search Intelligence
     */
    public function getGlobalSearchResultDetails(): array
    {
        return [
            'Table' => $this->table_name,
            'API' => $this->generate_api ? '✅' : '❌',
        ];
    }
}
