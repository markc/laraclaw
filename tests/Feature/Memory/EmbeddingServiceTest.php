<?php

use App\Services\Memory\EmbeddingService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = new EmbeddingService;
});

test('embed returns embedding vector from ollama', function () {
    Http::fake([
        '*/api/embed' => Http::response([
            'embeddings' => [array_fill(0, 768, 0.1)],
        ]),
    ]);

    $result = $this->service->embed('test text');

    expect($result)->toBeArray()->toHaveCount(768);
    Http::assertSentCount(1);
});

test('embed handles legacy ollama response format', function () {
    Http::fake([
        '*/api/embed' => Http::response([
            'embedding' => array_fill(0, 768, 0.05),
        ]),
    ]);

    $result = $this->service->embed('test text');

    expect($result)->toBeArray()->toHaveCount(768);
});

test('embed truncates content to max length', function () {
    config(['memory.embedding.max_content_length' => 10]);

    Http::fake([
        '*/api/embed' => Http::response([
            'embeddings' => [array_fill(0, 768, 0.1)],
        ]),
    ]);

    $this->service->embed(str_repeat('a', 100));

    Http::assertSent(function ($request) {
        return mb_strlen($request['input']) <= 10;
    });
});

test('embedBatch returns multiple vectors', function () {
    Http::fake([
        '*/api/embed' => Http::response([
            'embeddings' => [
                array_fill(0, 768, 0.1),
                array_fill(0, 768, 0.2),
            ],
        ]),
    ]);

    $result = $this->service->embedBatch(['text one', 'text two']);

    expect($result)->toBeArray()->toHaveCount(2);
    expect($result[0])->toHaveCount(768);
});

test('toVector formats embedding as postgresql vector literal', function () {
    $embedding = [0.1, 0.2, 0.3];

    $result = $this->service->toVector($embedding);

    expect($result)->toBe('[0.1,0.2,0.3]');
});

test('isAvailable returns true when ollama responds', function () {
    Http::fake([
        '*/api/tags' => Http::response(['models' => []]),
    ]);

    expect($this->service->isAvailable())->toBeTrue();
});

test('isAvailable returns false on connection failure', function () {
    Http::fake([
        '*/api/tags' => Http::response(status: 500),
    ]);

    expect($this->service->isAvailable())->toBeFalse();
});

test('embed throws on unexpected response format', function () {
    Http::fake([
        '*/api/embed' => Http::response(['unexpected' => 'data']),
    ]);

    expect(fn () => $this->service->embed('test'))
        ->toThrow(RuntimeException::class, 'Unexpected Ollama embed response format');
});
