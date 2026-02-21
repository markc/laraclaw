<?php

use App\Services\Tools\BuiltIn\BashTool;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new BashTool;
});

it('has correct name and description', function () {
    expect($this->tool->name())->toBe('bash')
        ->and($this->tool->description())->toContain('shell command');
});

it('executes a simple command', function () {
    $result = $this->tool->handle(new Request(['command' => 'echo hello']));

    expect($result)
        ->toContain('Exit code: 0')
        ->toContain('hello');
});

it('returns error for empty command', function () {
    $result = $this->tool->handle(new Request(['command' => '']));

    expect($result)->toContain('Error: command parameter is required');
});

it('blocks dangerous commands', function () {
    $result = $this->tool->handle(new Request(['command' => 'rm -rf /']));

    expect($result)->toContain('blocked for safety');
});

it('captures stderr', function () {
    $result = $this->tool->handle(new Request(['command' => 'ls /nonexistent_dir_xyz']));

    expect($result)
        ->toContain('Exit code:')
        ->toContain('Stderr:');
});

it('respects working directory', function () {
    $result = $this->tool->handle(new Request([
        'command' => 'pwd',
        'working_directory' => '/tmp',
    ]));

    expect($result)->toContain('/tmp');
});

it('has required schema parameters', function () {
    $schema = $this->tool->schema(new \Illuminate\JsonSchema\JsonSchemaTypeFactory);

    expect($schema)->toBeArray()->not->toBeEmpty();
});
