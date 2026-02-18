<?php

use App\Jobs\ProcessScheduledAction;
use App\Models\Agent;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create();
});

test('due actions are dispatched', function () {
    Queue::fake();

    ScheduledAction::factory()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'next_run_at' => now()->subMinute(),
    ]);

    $this->artisan('agent:heartbeat')
        ->expectsOutputToContain('dispatched 1 actions')
        ->assertSuccessful();

    Queue::assertPushed(ProcessScheduledAction::class, 1);
});

test('actions with null next_run_at are dispatched on first run', function () {
    Queue::fake();

    ScheduledAction::factory()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'next_run_at' => null,
    ]);

    $this->artisan('agent:heartbeat')
        ->expectsOutputToContain('dispatched 1 actions')
        ->assertSuccessful();

    Queue::assertPushed(ProcessScheduledAction::class, 1);
});

test('not-due actions are skipped', function () {
    Queue::fake();

    ScheduledAction::factory()->notDue()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
    ]);

    $this->artisan('agent:heartbeat')
        ->expectsOutputToContain('dispatched 0 actions')
        ->assertSuccessful();

    Queue::assertNotPushed(ProcessScheduledAction::class);
});

test('disabled actions are skipped', function () {
    Queue::fake();

    ScheduledAction::factory()->disabled()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'next_run_at' => now()->subMinute(),
    ]);

    $this->artisan('agent:heartbeat')
        ->expectsOutputToContain('dispatched 0 actions')
        ->assertSuccessful();

    Queue::assertNotPushed(ProcessScheduledAction::class);
});

test('next_run_at is computed after dispatch', function () {
    Queue::fake();

    $action = ScheduledAction::factory()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'schedule' => '0 8 * * *',
        'next_run_at' => now()->subMinute(),
    ]);

    $this->artisan('agent:heartbeat')->assertSuccessful();

    $action->refresh();
    expect($action->next_run_at)->not->toBeNull()
        ->and($action->next_run_at->isFuture())->toBeTrue();
});

test('last_run_at is updated after dispatch', function () {
    Queue::fake();

    $action = ScheduledAction::factory()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'last_run_at' => null,
        'next_run_at' => now()->subMinute(),
    ]);

    $this->artisan('agent:heartbeat')->assertSuccessful();

    $action->refresh();
    expect($action->last_run_at)->not->toBeNull();
});

test('dispatched job receives the correct action', function () {
    Queue::fake();

    $action = ScheduledAction::factory()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'next_run_at' => now()->subMinute(),
    ]);

    $this->artisan('agent:heartbeat')->assertSuccessful();

    Queue::assertPushed(ProcessScheduledAction::class, function ($job) use ($action) {
        return $job->action->id === $action->id;
    });
});

test('multiple due actions are all dispatched', function () {
    Queue::fake();

    ScheduledAction::factory()->count(3)->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'next_run_at' => now()->subMinute(),
    ]);

    $this->artisan('agent:heartbeat')
        ->expectsOutputToContain('dispatched 3 actions')
        ->assertSuccessful();

    Queue::assertPushed(ProcessScheduledAction::class, 3);
});
