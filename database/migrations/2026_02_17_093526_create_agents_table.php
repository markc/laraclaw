<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('model')->default('claude-sonnet-4-5-20250929');
            $table->string('provider')->default('anthropic');
            $table->json('config')->default('{}');
            $table->string('workspace_path')->nullable();
            $table->json('tool_policy')->default('{}');
            $table->json('prompt_overrides')->default('{}');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
