<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('agent_sessions')->cascadeOnDelete();
            $table->string('role', 20); // user, assistant, system, tool
            $table->longText('content');
            $table->json('attachments')->default('[]');
            $table->json('tool_calls')->default('[]');
            $table->json('tool_results')->default('[]');
            $table->json('usage')->default('{}'); // input_tokens, output_tokens
            $table->json('meta')->default('{}'); // model, provider, duration_ms, etc.
            $table->timestamps();

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};
