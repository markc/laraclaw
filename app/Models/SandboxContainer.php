<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SandboxContainer extends Model
{
    protected $fillable = [
        'vmid',
        'node',
        'ip_address',
        'status',
        'claimed_by_job',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
        ];
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', 'ready');
    }

    public function scopeBusy(Builder $query): Builder
    {
        return $query->where('status', 'busy');
    }
}
