<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolExecution extends Model
{
    protected $fillable = [
        'session_id',
        'message_id',
        'tool_name',
        'parameters',
        'status',
        'result',
        'error',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'result' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'session_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AgentMessage::class, 'message_id');
    }
}
