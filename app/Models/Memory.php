<?php

namespace App\Models;

use App\Jobs\GenerateMemoryEmbedding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Memory extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'session_id',
        'content',
        'embedding',
        'metadata',
        'memory_type',
        'source_file',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Memory $memory) {
            if (config('memory.auto_index.enabled', true)) {
                GenerateMemoryEmbedding::dispatch($memory);
            }
        });

        static::updated(function (Memory $memory) {
            if (config('memory.auto_index.enabled', true) && $memory->wasChanged('content')) {
                GenerateMemoryEmbedding::dispatch($memory);
            }
        });
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'session_id');
    }
}
