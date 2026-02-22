<?php

use App\Jobs\ProcessScheduledAction;
use App\Models\Agent;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create();
});

test('webhook endpoint dispatches matching routine', function () {
    $routine = ScheduledAction::factory()->webhookTriggered()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->postJson("/api/routines/webhook/{$routine->webhook_token}", [
        'event' => 'deploy',
        'repo' => 'laraclaw',
    ]);

    $response->assertStatus(202);

    Queue::assertPushed(ProcessScheduledAction::class, function ($job) use ($routine) {
        return $job->action->id === $routine->id
            && $job->triggerContext['trigger'] === 'webhook';
    });
});

test('webhook endpoint returns 404 for invalid token', function () {
    $response = $this->postJson('/api/routines/webhook/nonexistent-token');

    $response->assertStatus(404);
    Queue::assertNotPushed(ProcessScheduledAction::class);
});

test('webhook endpoint returns 429 during cooldown', function () {
    $routine = ScheduledAction::factory()->webhookTriggered()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'cooldown_seconds' => 300,
        'last_run_at' => now(),
    ]);

    $response = $this->postJson("/api/routines/webhook/{$routine->webhook_token}");

    $response->assertStatus(429);
    Queue::assertNotPushed(ProcessScheduledAction::class);
});

test('disabled webhook routine returns 404', function () {
    $routine = ScheduledAction::factory()->webhookTriggered()->disabled()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
    ]);

    $response = $this->postJson("/api/routines/webhook/{$routine->webhook_token}");

    $response->assertStatus(404);
});

test('webhook updates last_run_at timestamp', function () {
    $routine = ScheduledAction::factory()->webhookTriggered()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'last_run_at' => null,
    ]);

    $this->postJson("/api/routines/webhook/{$routine->webhook_token}");

    expect($routine->fresh()->last_run_at)->not->toBeNull();
});
