<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_key')->unique();
            $table->string('title')->default('New Chat');
            $table->string('channel', 50); // web, tui, email
            $table->string('trust_level', 20)->default('standard'); // operator, standard, restricted
            $table->string('model')->nullable(); // override agent default
            $table->string('provider')->nullable();
            $table->text('system_prompt')->nullable();
            $table->json('state')->default('{}');
            $table->text('compacted_summary')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'channel']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_sessions');
    }
};
