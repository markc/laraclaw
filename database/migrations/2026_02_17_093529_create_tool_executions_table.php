<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('agent_sessions')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('agent_messages')->nullOnDelete();
            $table->string('tool_name');
            $table->json('parameters')->default('{}');
            $table->string('status', 20)->default('pending'); // pending, running, success, error
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_executions');
    }
};
