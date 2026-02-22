<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Memory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Memory>
 */
class MemoryFactory extends Factory
{
    protected $model = Memory::class;

    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'content' => fake()->paragraph(),
            'metadata' => [],
            'memory_type' => 'conversation',
        ];
    }

    public function conversation(): static
    {
        return $this->state(fn () => [
            'memory_type' => 'conversation',
        ]);
    }

    public function dailyNote(): static
    {
        return $this->state(fn () => [
            'memory_type' => 'daily_note',
            'content' => fake()->paragraphs(3, true),
        ]);
    }

    public function file(): static
    {
        return $this->state(fn () => [
            'memory_type' => 'file',
            'source_file' => fake()->filePath(),
            'content_hash' => fake()->sha256(),
        ]);
    }
}
