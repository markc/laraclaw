<?php

namespace App\Services\Memory;

use App\Models\Memory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemorySearchService
{
    public function __construct(
        protected EmbeddingService $embeddingService,
    ) {}

    /**
     * Hybrid search combining vector similarity and keyword matching via RRF.
     *
     * @return Collection<int, Memory>
     */
    public function search(string $query, int $agentId, int $limit = 10): Collection
    {
        $k = config('memory.search.rrf_k', 60);
        $candidateMultiplier = config('memory.search.candidate_multiplier', 3);
        $candidateLimit = $limit * $candidateMultiplier;

        $vectorResults = $this->vectorSearch($query, $agentId, $candidateLimit);
        $keywordResults = $this->keywordSearch($query, $agentId, $candidateLimit);

        return $this->reciprocalRankFusion($vectorResults, $keywordResults, $k, $limit);
    }

    /**
     * Vector similarity search using pgvector cosine distance.
     *
     * @return Collection<int, array{id: int, rank: int}>
     */
    public function vectorSearch(string $query, int $agentId, int $limit): Collection
    {
        try {
            $embedding = $this->embeddingService->embed($query);
            $vector = $this->embeddingService->toVector($embedding);

            $results = DB::select('
                SELECT id, embedding <=> ?::vector AS distance
                FROM memories
                WHERE agent_id = ? AND embedding IS NOT NULL
                ORDER BY embedding <=> ?::vector
                LIMIT ?
            ', [$vector, $agentId, $vector, $limit]);

            return collect($results)->values()->map(fn ($row, $index) => [
                'id' => $row->id,
                'rank' => $index + 1,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Memory vector search failed, falling back to keyword-only', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Full-text keyword search using PostgreSQL tsvector/tsquery.
     *
     * @return Collection<int, array{id: int, rank: int}>
     */
    public function keywordSearch(string $query, int $agentId, int $limit): Collection
    {
        // Convert query to tsquery — use plainto_tsquery for natural language input
        $results = DB::select("
            SELECT id, ts_rank_cd(content_tsv, plainto_tsquery('english', ?)) AS rank_score
            FROM memories
            WHERE agent_id = ? AND content_tsv @@ plainto_tsquery('english', ?)
            ORDER BY rank_score DESC
            LIMIT ?
        ", [$query, $agentId, $query, $limit]);

        return collect($results)->values()->map(fn ($row, $index) => [
            'id' => $row->id,
            'rank' => $index + 1,
        ]);
    }

    /**
     * Merge ranked result lists using Reciprocal Rank Fusion.
     *
     * score = sum(weight / (k + rank)) for each list the document appears in
     *
     * @param  Collection<int, array{id: int, rank: int}>  $vectorResults
     * @param  Collection<int, array{id: int, rank: int}>  $keywordResults
     * @return Collection<int, Memory>
     */
    public function reciprocalRankFusion(
        Collection $vectorResults,
        Collection $keywordResults,
        int $k,
        int $limit,
    ): Collection {
        $vectorWeight = config('memory.search.vector_weight', 0.7);
        $keywordWeight = config('memory.search.keyword_weight', 0.3);

        $scores = [];

        foreach ($vectorResults as $result) {
            $id = $result['id'];
            $scores[$id] = ($scores[$id] ?? 0) + ($vectorWeight / ($k + $result['rank']));
        }

        foreach ($keywordResults as $result) {
            $id = $result['id'];
            $scores[$id] = ($scores[$id] ?? 0) + ($keywordWeight / ($k + $result['rank']));
        }

        // Sort by score descending, take top N
        arsort($scores);
        $topIds = array_slice(array_keys($scores), 0, $limit);

        if (empty($topIds)) {
            return collect();
        }

        // Load Memory models preserving score order
        $memories = Memory::whereIn('id', $topIds)->get()->keyBy('id');

        return collect($topIds)
            ->map(fn ($id) => $memories->get($id))
            ->filter();
    }
}
