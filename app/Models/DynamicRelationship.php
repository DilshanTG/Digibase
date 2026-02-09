<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'dynamic_model_id',
        'related_model_id',
        'name',
        'type',
        'foreign_key',
        'local_key',
        'pivot_table',
        'method_name', // 👈 THIS WAS MISSING (Crucial Fix)
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function dynamicModel(): BelongsTo
    {
        return $this->belongsTo(DynamicModel::class);
    }

    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(DynamicModel::class, 'related_model_id');
    }
}
