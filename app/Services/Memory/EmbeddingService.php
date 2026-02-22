<?php

namespace App\Services\Memory;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    /**
     * Generate an embedding vector for a single text.
     *
     * @return array<float>
     */
    public function embed(string $text): array
    {
        $text = mb_substr($text, 0, config('memory.embedding.max_content_length', 8000));

        $response = Http::timeout(config('memory.embedding.timeout', 30))
            ->post($this->baseUrl().'/api/embed', [
                'model' => config('memory.embedding_model'),
                'input' => $text,
            ]);

        $response->throw();

        $data = $response->json();

        // Ollama returns embeddings in 'embeddings' array (newer) or 'embedding' (older)
        if (isset($data['embeddings'][0])) {
            return $data['embeddings'][0];
        }

        if (isset($data['embedding'])) {
            return $data['embedding'];
        }

        throw new \RuntimeException('Unexpected Ollama embed response format');
    }

    /**
     * Generate embeddings for multiple texts in a single request.
     *
     * @param  array<string>  $texts
     * @return array<array<float>>
     */
    public function embedBatch(array $texts): array
    {
        $maxLength = config('memory.embedding.max_content_length', 8000);
        $texts = array_map(fn (string $t) => mb_substr($t, 0, $maxLength), $texts);

        $response = Http::timeout(config('memory.embedding.timeout', 30))
            ->post($this->baseUrl().'/api/embed', [
                'model' => config('memory.embedding_model'),
                'input' => $texts,
            ]);

        $response->throw();

        $data = $response->json();

        if (isset($data['embeddings'])) {
            return $data['embeddings'];
        }

        throw new \RuntimeException('Unexpected Ollama batch embed response format');
    }

    /**
     * Convert a float array to a PostgreSQL vector literal string.
     */
    public function toVector(array $embedding): string
    {
        return '['.implode(',', $embedding).']';
    }

    /**
     * Check if the embedding service is available.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl().'/api/tags');

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    protected function baseUrl(): string
    {
        return rtrim(config('memory.ollama_host'), '/');
    }
}
