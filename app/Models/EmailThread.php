<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailThread extends Model
{
    /** @use HasFactory<\Database\Factories\EmailThreadFactory> */
    use HasFactory;

    protected $fillable = [
        'session_id',
        'from_address',
        'to_address',
        'subject',
        'message_id',
        'in_reply_to',
        'references',
        'direction',
    ];

    protected function casts(): array
    {
        return [
            'references' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'session_id');
    }
}
