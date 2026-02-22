<?php

use App\Jobs\ProcessScheduledAction;
use App\Models\Agent;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\Routines\HealthMonitor;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create();
});

test('health check reports all clear when no issues', function () {
    $this->artisan('agent:health-check')
        ->expectsOutputToContain('all clear')
        ->assertSuccessful();
});

test('health monitor returns check structure', function () {
    $monitor = new HealthMonitor;
    $results = $monitor->runAll();

    expect($results)->toBeArray()->toHaveCount(4);

    foreach ($results as $result) {
        expect($result)->toHaveKeys(['type', 'count', 'details']);
    }
});

test('health check dispatches matching health routines', function () {
    // Create a stuck job (manually insert into jobs table)
    $payload = json_encode(['displayName' => 'TestJob']);
    \Illuminate\Support\Facades\DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => $payload,
        'attempts' => 1,
        'reserved_at' => now()->subHour()->timestamp,
        'available_at' => now()->subHour()->timestamp,
        'created_at' => now()->subHour()->timestamp,
    ]);

    // Create a health routine that watches for stuck jobs
    $routine = ScheduledAction::factory()->healthTriggered()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'health_check' => ['type' => 'stuck_jobs', 'threshold_minutes' => 30],
    ]);

    $this->artisan('agent:health-check')
        ->expectsOutputToContain('issue')
        ->assertSuccessful();

    Queue::assertPushed(ProcessScheduledAction::class, function ($job) use ($routine) {
        return $job->action->id === $routine->id
            && $job->triggerContext['trigger'] === 'health';
    });
});

test('health check skips routines with active cooldown', function () {
    \Illuminate\Support\Facades\DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'TestJob']),
        'attempts' => 1,
        'reserved_at' => now()->subHour()->timestamp,
        'available_at' => now()->subHour()->timestamp,
        'created_at' => now()->subHour()->timestamp,
    ]);

    ScheduledAction::factory()->healthTriggered()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'health_check' => ['type' => 'stuck_jobs'],
        'cooldown_seconds' => 3600,
        'last_run_at' => now(),
    ]);

    $this->artisan('agent:health-check')->assertSuccessful();

    Queue::assertNotPushed(ProcessScheduledAction::class);
});
