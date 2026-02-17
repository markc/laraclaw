<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('agent_sessions')->cascadeOnDelete();
            $table->string('from_address');
            $table->string('to_address');
            $table->string('subject');
            $table->string('message_id')->unique(); // RFC 2822 Message-ID
            $table->string('in_reply_to')->nullable();
            $table->json('references')->default('[]'); // thread chain
            $table->string('direction', 10); // inbound, outbound
            $table->timestamps();

            $table->index('from_address');
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_threads');
    }
};
