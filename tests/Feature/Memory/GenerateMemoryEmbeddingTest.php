<?php

use App\Jobs\GenerateMemoryEmbedding;
use App\Models\Agent;
use App\Models\Memory;
use App\Services\Memory\EmbeddingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->agent = Agent::factory()->create();
});

test('job stores embedding in database', function () {
    $memory = Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'Test memory content',
    ]);

    $embedding = array_fill(0, 768, 0.1);

    $embeddingService = Mockery::mock(EmbeddingService::class);
    $embeddingService->shouldReceive('embed')
        ->with('Test memory content')
        ->andReturn($embedding);
    $embeddingService->shouldReceive('toVector')
        ->with($embedding)
        ->andReturn('['.implode(',', $embedding).']');

    $job = new GenerateMemoryEmbedding($memory);
    $job->handle($embeddingService);

    // Verify embedding was stored
    $row = DB::selectOne('SELECT embedding IS NOT NULL as has_embedding FROM memories WHERE id = ?', [$memory->id]);
    expect($row->has_embedding)->toBeTrue();
});

test('creating a memory auto-dispatches embedding job when enabled', function () {
    config(['memory.auto_index.enabled' => true]);

    Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'New memory to embed',
    ]);

    Queue::assertPushed(GenerateMemoryEmbedding::class);
});

test('creating a memory does not dispatch job when disabled', function () {
    config(['memory.auto_index.enabled' => false]);

    Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'Memory without embedding',
    ]);

    Queue::assertNotPushed(GenerateMemoryEmbedding::class);
});

test('updating memory content re-dispatches embedding job', function () {
    config(['memory.auto_index.enabled' => true]);

    $memory = Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'Original content',
    ]);

    Queue::assertPushed(GenerateMemoryEmbedding::class, 1);

    $memory->update(['content' => 'Updated content']);

    Queue::assertPushed(GenerateMemoryEmbedding::class, 2);
});

test('updating non-content fields does not re-dispatch embedding job', function () {
    config(['memory.auto_index.enabled' => true]);

    $memory = Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'Some content',
    ]);

    Queue::assertPushed(GenerateMemoryEmbedding::class, 1);

    $memory->update(['memory_type' => 'fact']);

    // Still only 1 dispatch (from create)
    Queue::assertPushed(GenerateMemoryEmbedding::class, 1);
});

test('job has correct retry configuration', function () {
    $memory = Memory::factory()->create([
        'agent_id' => $this->agent->id,
    ]);

    $job = new GenerateMemoryEmbedding($memory);

    expect($job->tries)->toBe(3);
    expect($job->backoff())->toBe([10, 30, 60]);
});
