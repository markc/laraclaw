<?php

use App\Events\MemorySaved;
use App\Jobs\ProcessScheduledAction;
use App\Models\Agent;
use App\Models\Memory;
use App\Models\ScheduledAction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Cache::flush();
    $this->user = User::factory()->create();
    $this->agent = Agent::factory()->create();
});

test('event-triggered routine dispatches on matching event', function () {
    $routine = ScheduledAction::factory()->eventTriggered()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'event_class' => MemorySaved::class,
        'event_filter' => [],
    ]);

    // Clear the watched events cache
    Cache::forget('routine_watched_events');

    $memory = Memory::factory()->create(['agent_id' => $this->agent->id]);
    event(new MemorySaved($memory));

    Queue::assertPushed(ProcessScheduledAction::class, function ($job) use ($routine) {
        return $job->action->id === $routine->id;
    });
});

test('event-triggered routine respects filter', function () {
    ScheduledAction::factory()->eventTriggered()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'event_class' => MemorySaved::class,
        'event_filter' => ['0.memory_type' => 'daily_note'],
    ]);

    Cache::forget('routine_watched_events');

    // Conversation memory should NOT trigger the routine
    $memory = Memory::factory()->conversation()->create(['agent_id' => $this->agent->id]);
    event(new MemorySaved($memory));

    Queue::assertNotPushed(ProcessScheduledAction::class);
});

test('disabled event routines are not triggered', function () {
    ScheduledAction::factory()->eventTriggered()->disabled()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'event_class' => MemorySaved::class,
    ]);

    Cache::forget('routine_watched_events');

    $memory = Memory::factory()->create(['agent_id' => $this->agent->id]);
    event(new MemorySaved($memory));

    Queue::assertNotPushed(ProcessScheduledAction::class);
});

test('cooldown prevents rapid re-triggering', function () {
    $routine = ScheduledAction::factory()->eventTriggered()->create([
        'agent_id' => $this->agent->id,
        'user_id' => $this->user->id,
        'event_class' => MemorySaved::class,
        'event_filter' => [],
        'cooldown_seconds' => 300,
        'last_run_at' => now(), // Just ran
    ]);

    Cache::forget('routine_watched_events');

    $memory = Memory::factory()->create(['agent_id' => $this->agent->id]);
    event(new MemorySaved($memory));

    Queue::assertNotPushed(ProcessScheduledAction::class);
});
