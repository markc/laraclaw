<?php

use App\DTOs\IncomingMessage;
use App\Enums\IntentType;
use App\Services\Agent\IntentRouter;

beforeEach(function () {
    $this->router = app(IntentRouter::class);
});

test('slash commands are classified as Command type', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: 'web.1.test',
        content: '/help',
    );

    $intent = $this->router->classify($message);

    expect($intent->type)->toBe(IntentType::Command);
    expect($intent->commandName)->toBe('help');
    expect($intent->isShortCircuit())->toBeTrue();
});

test('unknown commands return error response', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: 'web.1.test',
        content: '/nonexistent',
    );

    $intent = $this->router->classify($message);

    expect($intent->type)->toBe(IntentType::Command);
    expect($intent->commandName)->toBe('nonexistent');
    expect($intent->response)->toContain('Unknown command');
});

test('questions are classified as Query', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: 'web.1.test',
        content: 'What is the capital of France?',
    );

    $intent = $this->router->classify($message);

    expect($intent->type)->toBe(IntentType::Query);
    expect($intent->isShortCircuit())->toBeFalse();
});

test('imperative sentences are classified as Task', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: 'web.1.test',
        content: 'Create a new Laravel controller for user authentication',
    );

    $intent = $this->router->classify($message);

    expect($intent->type)->toBe(IntentType::Task);
});

test('long messages are classified as Task', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: 'web.1.test',
        content: str_repeat('Please do this thing. ', 30),
    );

    $intent = $this->router->classify($message);

    expect($intent->type)->toBe(IntentType::Task);
});

test('messages with code blocks are classified as Task', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: 'web.1.test',
        content: "Fix this code:\n```php\nfunction broken() {}\n```",
    );

    $intent = $this->router->classify($message);

    expect($intent->type)->toBe(IntentType::Task);
});

test('parseCommand extracts name and args', function () {
    $parsed = $this->router->parseCommand('/model claude-opus-4-6');

    expect($parsed['name'])->toBe('model');
    expect($parsed['args'])->toBe(['claude-opus-4-6']);
});

test('parseCommand handles command with no args', function () {
    $parsed = $this->router->parseCommand('/help');

    expect($parsed['name'])->toBe('help');
    expect($parsed['args'])->toBe([]);
});

test('parseCommand handles extra whitespace', function () {
    $parsed = $this->router->parseCommand('  /rename   My New Title  ');

    expect($parsed['name'])->toBe('rename');
    expect($parsed['args'])->toBe(['My', 'New', 'Title']);
});

test('command aliases resolve correctly', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: 'web.1.test',
        content: '/title New Title',
    );

    $intent = $this->router->classify($message);

    // /title is an alias for /rename
    expect($intent->commandName)->toBe('rename');
});

test('getHandlers returns unique handlers', function () {
    $handlers = $this->router->getHandlers();

    expect($handlers)->toHaveKeys(['model', 'rename', 'help', 'info', 'new']);
    expect(count($handlers))->toBe(5);
});

test('short question-mark message is classified as Query', function () {
    $message = new IncomingMessage(
        channel: 'web',
        sessionKey: 'web.1.test',
        content: 'How does routing work?',
    );

    $intent = $this->router->classify($message);

    expect($intent->type)->toBe(IntentType::Query);
});
