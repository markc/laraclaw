<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        // Convert embedding column from text to vector(768) for nomic-embed-text
        DB::statement('ALTER TABLE memories ALTER COLUMN embedding TYPE vector(768) USING embedding::vector(768)');

        // Add IVFFlat index for efficient similarity search
        DB::statement('CREATE INDEX IF NOT EXISTS memories_embedding_idx ON memories USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS memories_embedding_idx');
        DB::statement('ALTER TABLE memories ALTER COLUMN embedding TYPE text USING embedding::text');
    }
};
