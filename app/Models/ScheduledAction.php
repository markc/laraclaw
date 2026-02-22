<?php

namespace App\Models;

use App\Enums\TriggerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'user_id',
        'name',
        'schedule',
        'prompt',
        'trigger_type',
        'event_class',
        'event_filter',
        'webhook_token',
        'health_check',
        'max_retries',
        'retry_count',
        'last_error',
        'last_duration_ms',
        'cooldown_seconds',
        'metadata',
        'session_key',
        'is_enabled',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => TriggerType::class,
            'event_filter' => 'array',
            'health_check' => 'array',
            'metadata' => 'array',
            'is_enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
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

    /**
     * Scope to cron-type routines only.
     */
    public function scopeCron(Builder $query): Builder
    {
        return $query->where('trigger_type', TriggerType::Cron);
    }

    /**
     * Scope to event-type routines only.
     */
    public function scopeEvent(Builder $query): Builder
    {
        return $query->where('trigger_type', TriggerType::Event);
    }

    /**
     * Scope to webhook-type routines only.
     */
    public function scopeWebhook(Builder $query): Builder
    {
        return $query->where('trigger_type', TriggerType::Webhook);
    }

    /**
     * Scope to health-type routines only.
     */
    public function scopeHealth(Builder $query): Builder
    {
        return $query->where('trigger_type', TriggerType::Health);
    }

    /**
     * Check if cooldown period has elapsed since last run.
     */
    public function isCooldownElapsed(): bool
    {
        if ($this->cooldown_seconds <= 0) {
            return true;
        }

        if (! $this->last_run_at) {
            return true;
        }

        return $this->last_run_at->addSeconds($this->cooldown_seconds)->isPast();
    }
}
