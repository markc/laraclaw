<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tool extends Model
{
    protected $fillable = [
        'name',
        'class_name',
        'description',
        'parameters_schema',
        'default_policy',
        'is_enabled',
        'is_builtin',
    ];

    protected function casts(): array
    {
        return [
            'parameters_schema' => 'array',
            'default_policy' => 'array',
            'is_enabled' => 'boolean',
            'is_builtin' => 'boolean',
        ];
    }
}
