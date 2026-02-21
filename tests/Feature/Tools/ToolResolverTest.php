<?php

use App\Models\AgentSession;
use App\Services\Tools\ToolResolver;

beforeEach(function () {
    $this->resolver = new ToolResolver;
});

it('resolves all tools for operator trust level', function () {
    $session = AgentSession::factory()->create(['trust_level' => 'operator']);

    $tools = $this->resolver->resolve($session);
    $names = collect($tools)->map(fn ($t) => $t->name())->all();

    expect($names)->toContain('current_datetime', 'http_request', 'bash');
});

it('resolves standard tools without bash', function () {
    $session = AgentSession::factory()->email()->create();

    $tools = $this->resolver->resolve($session);
    $names = collect($tools)->map(fn ($t) => $t->name())->all();

    expect($names)
        ->toContain('current_datetime', 'http_request')
        ->not->toContain('bash');
});

it('resolves only current_datetime for restricted trust level', function () {
    $session = AgentSession::factory()->create(['trust_level' => 'restricted']);

    $tools = $this->resolver->resolve($session);
    $names = collect($tools)->map(fn ($t) => $t->name())->all();

    expect($names)
        ->toContain('current_datetime')
        ->not->toContain('http_request', 'bash');
});

it('falls back to channel config trust level when session trust not set', function () {
    $session = AgentSession::factory()->create([
        'channel' => 'email',
        'trust_level' => 'standard',
    ]);

    $tools = $this->resolver->resolve($session);
    $names = collect($tools)->map(fn ($t) => $t->name())->all();

    // email channel uses standard trust
    expect($names)
        ->toContain('current_datetime', 'http_request')
        ->not->toContain('bash');
});

it('returns tool instances implementing the Tool contract', function () {
    $session = AgentSession::factory()->create(['trust_level' => 'operator']);

    $tools = $this->resolver->resolve($session);

    foreach ($tools as $tool) {
        expect($tool)->toBeInstanceOf(\Laravel\Ai\Contracts\Tool::class);
    }
});
