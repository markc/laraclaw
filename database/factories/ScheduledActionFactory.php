<?php

namespace Database\Factories;

use App\Enums\TriggerType;
use App\Models\Agent;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScheduledAction>
 */
class ScheduledActionFactory extends Factory
{
    protected $model = ScheduledAction::class;

    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'schedule' => '0 8 * * *',
            'prompt' => fake()->sentence(),
            'trigger_type' => TriggerType::Cron,
            'session_key' => null,
            'is_enabled' => true,
            'last_run_at' => null,
            'next_run_at' => now()->subMinute(),
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => [
            'is_enabled' => false,
        ]);
    }

    public function notDue(): static
    {
        return $this->state(fn () => [
            'next_run_at' => now()->addHour(),
        ]);
    }

    public function withSessionKey(string $key): static
    {
        return $this->state(fn () => [
            'session_key' => $key,
        ]);
    }

    public function eventTriggered(): static
    {
        return $this->state(fn () => [
            'trigger_type' => TriggerType::Event,
            'schedule' => '',
            'event_class' => 'App\Events\MemorySaved',
            'event_filter' => ['memory_type' => 'conversation'],
        ]);
    }

    public function webhookTriggered(): static
    {
        return $this->state(fn () => [
            'trigger_type' => TriggerType::Webhook,
            'schedule' => '',
            'webhook_token' => Str::random(64),
        ]);
    }

    public function healthTriggered(): static
    {
        return $this->state(fn () => [
            'trigger_type' => TriggerType::Health,
            'schedule' => '',
            'health_check' => ['type' => 'stuck_jobs', 'threshold_minutes' => 30],
        ]);
    }
}
