<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicField extends Model
{
    use HasFactory;

    protected $fillable = [
        'dynamic_model_id',
        'name',
        'display_name',
        'type',
        'description',
        'is_required',
        'is_unique',
        'is_indexed',
        'is_searchable',
        'is_filterable',
        'is_sortable',
        'show_in_list',
        'show_in_detail',
        'default_value',
        'validation_rules',
        'options',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'is_indexed' => 'boolean',
            'is_searchable' => 'boolean',
            'is_filterable' => 'boolean',
            'is_sortable' => 'boolean',
            'show_in_list' => 'boolean',
            'show_in_detail' => 'boolean',
            'validation_rules' => 'array',
            'options' => 'array',
        ];
    }

    public function dynamicModel(): BelongsTo
    {
        return $this->belongsTo(DynamicModel::class);
    }

    /**
     * Get the database column type for this field type.
     */
    public function getDatabaseType(): string
    {
        return match ($this->type) {
            'string', 'email', 'url', 'phone', 'slug', 'password', 'color', 'encrypted' => 'string',
            'text', 'richtext', 'markdown' => 'text',
            'integer' => 'integer',
            'bigint' => 'bigInteger',
            'float' => 'float',
            'decimal', 'money' => 'decimal',
            'boolean', 'checkbox' => 'boolean',
            'date' => 'date',
            'datetime', 'timestamp' => 'dateTime',
            'time' => 'time',
            'json', 'array' => 'json',
            'uuid' => 'uuid',
            'enum', 'select' => 'string',
            'file', 'image' => 'string',
            'point' => 'geometry',
            default => 'string',
        };
    }
}
