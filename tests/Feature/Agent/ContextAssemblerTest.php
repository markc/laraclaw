<?php

use App\DTOs\IncomingMessage;
use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\Memory;
use App\Services\Agent\ContextAssembler;
use App\Services\Memory\EmbeddingService;
use App\Services\Memory\MemorySearchService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->agent = Agent::factory()->create();
    $this->session = AgentSession::factory()->create([
        'agent_id' => $this->agent->id,
    ]);
});

test('memories appear in system prompt when search enabled', function () {
    config(['memory.search.enabled' => true]);

    Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'User prefers dark mode themes',
    ]);

    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: $this->session->session_key,
        content: 'What theme do I prefer?',
        userId: $this->session->user_id,
    );

    $assembler = app(ContextAssembler::class);
    $context = $assembler->build($this->session, $message);

    // Memory should appear in system prompt (keyword match on "prefer"/"theme")
    expect($context['system'])->toContain('Relevant Memories');
    expect($context['system'])->toContain('User prefers dark mode themes');
});

test('no memory section when search disabled', function () {
    config(['memory.search.enabled' => false]);

    Memory::factory()->create([
        'agent_id' => $this->agent->id,
        'content' => 'User prefers dark mode themes',
    ]);

    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: $this->session->session_key,
        content: 'What theme do I prefer?',
        userId: $this->session->user_id,
    );

    $assembler = app(ContextAssembler::class);
    $context = $assembler->build($this->session, $message);

    expect($context['system'])->not->toContain('Relevant Memories');
});

test('context assembly succeeds when memory search fails', function () {
    config(['memory.search.enabled' => true]);

    // Mock embedding service to fail
    $mockEmbedding = Mockery::mock(EmbeddingService::class);
    $mockEmbedding->shouldReceive('embed')
        ->andThrow(new RuntimeException('Connection refused'));

    $mockSearch = new MemorySearchService($mockEmbedding);
    $this->app->instance(MemorySearchService::class, $mockSearch);

    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: $this->session->session_key,
        content: 'Test message',
        userId: $this->session->user_id,
    );

    $assembler = app(ContextAssembler::class);
    $context = $assembler->build($this->session, $message);

    // Should still build context without memories
    expect($context)->toHaveKeys(['system', 'messages']);
    expect($context['system'])->not->toContain('Relevant Memories');
});

test('context includes current message', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: $this->session->session_key,
        content: 'Hello agent',
        userId: $this->session->user_id,
    );

    $assembler = app(ContextAssembler::class);
    $context = $assembler->build($this->session, $message);

    $lastMessage = end($context['messages']);
    expect($lastMessage['role'])->toBe('user');
    expect($lastMessage['content'])->toBe('Hello agent');
});
