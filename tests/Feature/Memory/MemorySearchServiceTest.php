<?php

use App\Models\Agent;
use App\Models\Memory;
use App\Services\Memory\EmbeddingService;
use App\Services\Memory\MemorySearchService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake(); // Prevent embedding jobs from dispatching
    $this->agent = Agent::factory()->create();
});

test('keyword search finds matching memories', function () {
    Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'The Laravel framework provides excellent documentation',
    ]);

    Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'Python is also a great programming language',
    ]);

    $embeddingService = Mockery::mock(EmbeddingService::class);
    $service = new MemorySearchService($embeddingService);

    $results = $service->keywordSearch('Laravel documentation', $this->agent->id, 10);

    expect($results)->toHaveCount(1);
    expect($results->first()['rank'])->toBe(1);
});

test('keyword search returns empty for no matches', function () {
    Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'nothing related here',
    ]);

    $embeddingService = Mockery::mock(EmbeddingService::class);
    $service = new MemorySearchService($embeddingService);

    $results = $service->keywordSearch('quantum mechanics', $this->agent->id, 10);

    expect($results)->toBeEmpty();
});

test('keyword search scopes to agent id', function () {
    $otherAgent = Agent::factory()->create();

    Memory::factory()->create([
        'agent_id' => $otherAgent->id,
        'content' => 'Laravel framework documentation',
    ]);

    $embeddingService = Mockery::mock(EmbeddingService::class);
    $service = new MemorySearchService($embeddingService);

    $results = $service->keywordSearch('Laravel', $this->agent->id, 10);

    expect($results)->toBeEmpty();
});

test('reciprocal rank fusion merges vector and keyword results', function () {
    $memories = Memory::factory()->count(3)->create([
        'agent_id' => $this->agent->id,
    ]);

    $embeddingService = Mockery::mock(EmbeddingService::class);
    $service = new MemorySearchService($embeddingService);

    $vectorResults = collect([
        ['id' => $memories[0]->id, 'rank' => 1],
        ['id' => $memories[1]->id, 'rank' => 2],
    ]);

    $keywordResults = collect([
        ['id' => $memories[1]->id, 'rank' => 1],
        ['id' => $memories[2]->id, 'rank' => 2],
    ]);

    $fused = $service->reciprocalRankFusion($vectorResults, $keywordResults, 60, 3);

    // Memory[1] appears in both lists so should rank highest
    expect($fused)->toHaveCount(3);
    expect($fused->first()->id)->toBe($memories[1]->id);
});

test('reciprocal rank fusion returns empty when no results', function () {
    $embeddingService = Mockery::mock(EmbeddingService::class);
    $service = new MemorySearchService($embeddingService);

    $fused = $service->reciprocalRankFusion(collect(), collect(), 60, 10);

    expect($fused)->toBeEmpty();
});

test('search falls back to keyword-only when vector search fails', function () {
    Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'Laravel provides a robust routing system',
    ]);

    $embeddingService = Mockery::mock(EmbeddingService::class);
    $embeddingService->shouldReceive('embed')
        ->andThrow(new RuntimeException('Ollama unavailable'));

    $service = new MemorySearchService($embeddingService);

    $results = $service->search('Laravel routing', $this->agent->id, 10);

    expect($results)->toHaveCount(1);
});

test('vector search returns ranked results with embeddings', function () {
    // Insert memories with real embeddings via raw SQL
    $memory1 = Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'Vector search test one',
    ]);
    $memory2 = Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'Vector search test two',
    ]);

    // Insert fake embeddings
    $embedding1 = array_fill(0, 768, 0.1);
    $embedding2 = array_fill(0, 768, 0.2);
    insertMemoryWithEmbedding($memory1->id, $embedding1);
    insertMemoryWithEmbedding($memory2->id, $embedding2);

    $queryEmbedding = array_fill(0, 768, 0.15);

    $embeddingService = Mockery::mock(EmbeddingService::class);
    $embeddingService->shouldReceive('embed')->andReturn($queryEmbedding);
    $embeddingService->shouldReceive('toVector')
        ->andReturnUsing(fn ($e) => '['.implode(',', $e).']');

    $service = new MemorySearchService($embeddingService);

    $results = $service->vectorSearch('test', $this->agent->id, 10);

    expect($results)->toHaveCount(2);
    expect($results->first())->toHaveKeys(['id', 'rank']);
});
