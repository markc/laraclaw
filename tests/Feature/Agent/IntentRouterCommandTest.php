<?php

use App\DTOs\IncomingMessage;
use App\Events\SessionUpdated;
use App\Models\Agent;
use App\Models\AgentSession;
use App\Services\Agent\IntentRouter;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->router = app(IntentRouter::class);
    $this->agent = Agent::factory()->create();
    $this->session = AgentSession::factory()->create([
        'agent_id' => $this->agent->id,
    ]);
});

test('/help lists all registered commands', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: $this->session->session_key,
        content: '/help',
    );

    $intent = $this->router->classify($message, $this->session);

    expect($intent->response)->toContain('/model');
    expect($intent->response)->toContain('/rename');
    expect($intent->response)->toContain('/help');
    expect($intent->response)->toContain('/info');
    expect($intent->response)->toContain('/new');
});

test('/info shows session details', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: $this->session->session_key,
        content: '/info',
    );

    $intent = $this->router->classify($message, $this->session);

    expect($intent->response)
        ->toContain($this->session->session_key)
        ->toContain($this->session->title)
        ->toContain('Trust level:');
});

test('/info without session gives helpful message', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: 'web.1.nonexistent',
        content: '/info',
    );

    $intent = $this->router->classify($message, null);

    expect($intent->response)->toContain('No active session');
});

test('/rename updates session title and broadcasts event', function () {
    Event::fake([SessionUpdated::class]);

    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: $this->session->session_key,
        content: '/rename My New Chat Title',
    );

    $intent = $this->router->classify($message, $this->session);

    expect($intent->response)->toContain('My New Chat Title');
    expect($this->session->fresh()->title)->toBe('My New Chat Title');

    Event::assertDispatched(SessionUpdated::class);
});

test('/rename without args shows usage', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: $this->session->session_key,
        content: '/rename',
    );

    $intent = $this->router->classify($message, $this->session);

    expect($intent->response)->toContain('Usage:');
});

test('/model without args shows current model', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: $this->session->session_key,
        content: '/model',
    );

    $intent = $this->router->classify($message, $this->session);

    expect($intent->response)->toContain('Current model:');
});

test('/model with invalid model returns error', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: $this->session->session_key,
        content: '/model nonexistent-model',
    );

    $intent = $this->router->classify($message, $this->session);

    expect($intent->response)->toContain('Unknown model');
});

test('/new returns metadata for new session', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: $this->session->session_key,
        content: '/new',
    );

    $intent = $this->router->classify($message, $this->session);

    expect($intent->commandName)->toBe('new');
    expect($intent->metadata)->toHaveKey('action', 'new_session');
});
