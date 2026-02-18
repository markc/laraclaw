<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'name' => 'Test Agent',
            'slug' => fake()->unique()->slug(2),
            'model' => 'claude-sonnet-4-5-20250929',
            'provider' => 'anthropic',
            'is_default' => true,
        ];
    }
}
