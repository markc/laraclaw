<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Insert a fake 768-dimension embedding for a memory via raw SQL.
 *
 * @param  array<float>  $embedding
 */
function insertMemoryWithEmbedding(int $memoryId, array $embedding): void
{
    $vector = '['.implode(',', $embedding).']';
    DB::statement('UPDATE memories SET embedding = ?::vector WHERE id = ?', [$vector, $memoryId]);
}
