<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AgentSession>
 */
class AgentSessionFactory extends Factory
{
    protected $model = AgentSession::class;

    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'user_id' => User::factory(),
            'session_key' => 'web.'.fake()->randomDigit().'.'.Str::uuid(),
            'title' => fake()->sentence(3),
            'channel' => 'web',
            'trust_level' => 'operator',
            'last_activity_at' => now(),
        ];
    }

    public function email(): static
    {
        return $this->state(fn () => [
            'channel' => 'email',
            'trust_level' => 'standard',
            'session_key' => 'email.'.fake()->randomDigit().'.'.Str::uuid(),
        ]);
    }

    public function tui(): static
    {
        return $this->state(fn () => [
            'channel' => 'tui',
            'session_key' => 'tui.'.Str::uuid(),
        ]);
    }
}
