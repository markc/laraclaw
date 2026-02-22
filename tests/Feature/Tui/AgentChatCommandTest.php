<?php

use App\Models\AgentSession;
use App\Models\User;
use App\Services\Agent\AgentRuntime;

it('lists sessions with --list flag', function () {
    $user = User::factory()->create();
    AgentSession::factory()->tui()->create([
        'user_id' => $user->id,
        'title' => 'My TUI Chat',
        'last_activity_at' => now(),
    ]);

    $this->artisan('agent:chat --list')
        ->assertSuccessful()
        ->expectsOutputToContain('My TUI Chat');
});

it('shows no sessions message when none exist', function () {
    $this->artisan('agent:chat --list')
        ->assertSuccessful()
        ->expectsOutput('No TUI sessions found.');
});

it('creates a new session on first message', function () {
    $user = User::factory()->create();

    $mock = Mockery::mock(AgentRuntime::class);
    $mock->shouldReceive('handleMessage')
        ->once()
        ->withArgs(fn ($msg) => $msg->channel === 'tui'
            && $msg->content === 'Hello agent'
            && $msg->userId === $user->id
        )
        ->andReturn('Hello! How can I help you?');
    $this->app->instance(AgentRuntime::class, $mock);

    $this->artisan('agent:chat')
        ->expectsQuestion('You', 'Hello agent')
        ->expectsOutputToContain('Hello! How can I help you?')
        ->expectsQuestion('You', '/quit');
});

it('resumes an existing session with --session flag', function () {
    $user = User::factory()->create();
    $session = AgentSession::factory()->tui()->create([
        'user_id' => $user->id,
        'title' => 'Resumed Chat',
    ]);

    $mock = Mockery::mock(AgentRuntime::class);
    $mock->shouldReceive('handleMessage')
        ->once()
        ->withArgs(fn ($msg) => $msg->sessionKey === $session->session_key)
        ->andReturn('Welcome back!');
    $this->app->instance(AgentRuntime::class, $mock);

    $this->artisan("agent:chat --session={$session->session_key}")
        ->expectsOutputToContain('Resuming session')
        ->expectsQuestion('You', 'Continue our chat')
        ->expectsOutputToContain('Welcome back!')
        ->expectsQuestion('You', '/quit');
});

it('handles /help command via IntentRouter', function () {
    User::factory()->create();

    $this->artisan('agent:chat')
        ->expectsQuestion('You', '/help')
        ->expectsOutputToContain('Available commands')
        ->expectsQuestion('You', '/quit');
});

it('handles /new command to start fresh session', function () {
    User::factory()->create();

    $mock = Mockery::mock(AgentRuntime::class);
    $mock->shouldReceive('handleMessage')
        ->once()
        ->andReturn('Fresh start!');
    $this->app->instance(AgentRuntime::class, $mock);

    $this->artisan('agent:chat')
        ->expectsQuestion('You', '/new')
        ->expectsOutputToContain('Started new session')
        ->expectsQuestion('You', 'First message')
        ->expectsOutputToContain('Fresh start!')
        ->expectsQuestion('You', '/quit');
});

it('passes model override to IncomingMessage', function () {
    User::factory()->create();

    $mock = Mockery::mock(AgentRuntime::class);
    $mock->shouldReceive('handleMessage')
        ->once()
        ->withArgs(fn ($msg) => $msg->model === 'claude-opus-4-6')
        ->andReturn('Using Opus!');
    $this->app->instance(AgentRuntime::class, $mock);

    $this->artisan('agent:chat --model=claude-opus-4-6')
        ->expectsQuestion('You', 'Test model override')
        ->expectsOutputToContain('Using Opus!')
        ->expectsQuestion('You', '/quit');
});

it('handles unknown commands gracefully', function () {
    User::factory()->create();

    $this->artisan('agent:chat')
        ->expectsQuestion('You', '/foobar')
        ->expectsOutputToContain('Unknown command')
        ->expectsQuestion('You', '/quit');
});
