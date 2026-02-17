<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('agent_sessions')->nullOnDelete();
            $table->text('content');
            // embedding column: use pgvector VECTOR(1536) in production,
            // store as JSON text in SQLite for dev
            $table->text('embedding')->nullable();
            $table->json('metadata')->default('{}');
            $table->string('memory_type', 50)->default('conversation'); // conversation, fact, daily_note, file
            $table->string('source_file')->nullable(); // for file-based memories
            $table->string('content_hash', 64)->nullable(); // for change detection
            $table->timestamps();

            $table->index(['agent_id', 'memory_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
