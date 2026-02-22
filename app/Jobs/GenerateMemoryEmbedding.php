<?php

namespace App\Jobs;

use App\Models\Memory;
use App\Services\Memory\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMemoryEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        public Memory $memory,
    ) {}

    public function handle(EmbeddingService $embeddingService): void
    {
        $embedding = $embeddingService->embed($this->memory->content);
        $vector = $embeddingService->toVector($embedding);

        DB::statement(
            'UPDATE memories SET embedding = ?::vector WHERE id = ?',
            [$vector, $this->memory->id]
        );

        Log::debug('GenerateMemoryEmbedding: stored embedding', [
            'memory_id' => $this->memory->id,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateMemoryEmbedding failed', [
            'memory_id' => $this->memory->id,
            'error' => $e->getMessage(),
        ]);
    }
}
