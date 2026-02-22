<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add tsvector column for full-text search
        DB::statement('ALTER TABLE memories ADD COLUMN content_tsv tsvector');

        // Create GIN index for fast full-text search
        DB::statement('CREATE INDEX memories_content_tsv_idx ON memories USING gin (content_tsv)');

        // Create trigger to auto-populate content_tsv on INSERT/UPDATE
        DB::statement("
            CREATE OR REPLACE FUNCTION memories_content_tsv_trigger() RETURNS trigger AS $$
            BEGIN
                NEW.content_tsv := to_tsvector('english', COALESCE(NEW.content, ''));
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement('
            CREATE TRIGGER memories_content_tsv_update
            BEFORE INSERT OR UPDATE OF content ON memories
            FOR EACH ROW EXECUTE FUNCTION memories_content_tsv_trigger();
        ');

        // Backfill existing rows
        DB::statement("UPDATE memories SET content_tsv = to_tsvector('english', COALESCE(content, ''))");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS memories_content_tsv_update ON memories');
        DB::statement('DROP FUNCTION IF EXISTS memories_content_tsv_trigger()');
        DB::statement('DROP INDEX IF EXISTS memories_content_tsv_idx');
        DB::statement('ALTER TABLE memories DROP COLUMN IF EXISTS content_tsv');
    }
};
