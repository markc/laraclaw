<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScheduledActionSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $agent = Agent::default();

        if (! $user || ! $agent) {
            return;
        }

        ScheduledAction::firstOrCreate(
            ['name' => 'Daily check-in'],
            [
                'agent_id' => $agent->id,
                'user_id' => $user->id,
                'schedule' => '0 8 * * *',
                'prompt' => 'Review any pending tasks and provide a brief status summary.',
                'is_enabled' => true,
            ]
        );
    }
}
