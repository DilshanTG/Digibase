<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiAnalytics extends Model
{
    protected $table = 'api_analytics';
    protected $guarded = [];
    public $timestamps = false; // Using manual created_at in middleware

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
