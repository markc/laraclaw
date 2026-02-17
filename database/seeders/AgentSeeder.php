<?php

namespace Database\Seeders;

use App\Models\Agent;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    public function run(): void
    {
        Agent::firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default Agent',
                'slug' => 'default',
                'model' => config('agent.default_model', 'claude-sonnet-4-5-20250929'),
                'provider' => config('agent.default_provider', 'anthropic'),
                'is_default' => true,
            ]
        );
    }
}
