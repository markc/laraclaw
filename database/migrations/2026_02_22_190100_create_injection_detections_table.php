<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('injection_detections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->nullable()->constrained('agent_sessions')->nullOnDelete();
            $table->string('source', 50);
            $table->json('patterns_matched');
            $table->text('content_excerpt');
            $table->string('policy_applied', 20);
            $table->timestamp('created_at');

            $table->index('session_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('injection_detections');
    }
};
