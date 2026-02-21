<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentSession extends Model
{
    /** @use HasFactory<\Database\Factories\AgentSessionFactory> */
    use HasFactory;

    protected $table = 'agent_sessions';

    protected $fillable = [
        'agent_id',
        'user_id',
        'session_key',
        'title',
        'channel',
        'trust_level',
        'model',
        'provider',
        'system_prompt',
        'state',
        'compacted_summary',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'last_activity_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'session_id')->orderBy('created_at');
    }

    public function toolExecutions(): HasMany
    {
        return $this->hasMany(ToolExecution::class, 'session_id');
    }

    public function emailThreads(): HasMany
    {
        return $this->hasMany(EmailThread::class, 'session_id');
    }

    public function getEffectiveModel(): string
    {
        return $this->model ?? $this->agent->model;
    }

    public function getEffectiveProvider(): string
    {
        return $this->provider ?? $this->agent->provider;
    }
}
