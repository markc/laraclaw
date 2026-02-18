<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
}
