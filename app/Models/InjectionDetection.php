<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InjectionDetection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'source',
        'patterns_matched',
        'content_excerpt',
        'policy_applied',
    ];

    protected function casts(): array
    {
        return [
            'patterns_matched' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (InjectionDetection $detection) {
            $detection->created_at = $detection->created_at ?? now();
        });
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'session_id');
    }
}
