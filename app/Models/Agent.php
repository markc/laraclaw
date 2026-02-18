<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'model',
        'provider',
        'config',
        'workspace_path',
        'tool_policy',
        'prompt_overrides',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'tool_policy' => 'array',
            'prompt_overrides' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AgentSession::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    public function scheduledActions(): HasMany
    {
        return $this->hasMany(ScheduledAction::class);
    }

    public static function default(): static
    {
        return static::where('is_default', true)->firstOrFail();
    }
}
