<?php

use App\Services\Tools\BuiltIn\HttpRequestTool;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->tool = new HttpRequestTool;
});

it('has correct name and description', function () {
    expect($this->tool->name())->toBe('http_request')
        ->and($this->tool->description())->toContain('HTTP request');
});

it('returns error for empty url', function () {
    $result = $this->tool->handle(new Request(['url' => '']));

    expect($result)->toContain('Error: url parameter is required');
});

it('blocks requests to localhost', function () {
    $result = $this->tool->handle(new Request(['url' => 'http://localhost/secret']));

    expect($result)->toContain('private/internal addresses');
});

it('blocks requests to 127.0.0.1', function () {
    $result = $this->tool->handle(new Request(['url' => 'http://127.0.0.1:8080/admin']));

    expect($result)->toContain('private/internal addresses');
});

it('makes a GET request successfully', function () {
    Http::fake(['https://example.com' => Http::response('Hello World', 200)]);

    $result = $this->tool->handle(new Request(['url' => 'https://example.com']));

    expect($result)
        ->toContain('HTTP 200')
        ->toContain('Hello World');
});

it('makes a POST request successfully', function () {
    Http::fake(['https://example.com/data' => Http::response('{"ok":true}', 201)]);

    $result = $this->tool->handle(new Request([
        'url' => 'https://example.com/data',
        'method' => 'POST',
        'body' => '{"key":"value"}',
    ]));

    expect($result)->toContain('HTTP 201');
});

it('truncates large responses', function () {
    Http::fake(['https://example.com' => Http::response(str_repeat('x', 10000), 200)]);

    $result = $this->tool->handle(new Request(['url' => 'https://example.com']));

    expect($result)->toContain('[truncated]');
});

it('has required schema parameters', function () {
    $schema = $this->tool->schema(new \Illuminate\JsonSchema\JsonSchemaTypeFactory);

    expect($schema)->toBeArray()->not->toBeEmpty();
});
