<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sandbox_containers', function (Blueprint $table) {
            $table->id();
            $table->integer('vmid')->unique();
            $table->string('node', 50);
            $table->string('ip_address', 45)->nullable();
            $table->string('status', 20)->default('provisioning'); // ready, busy, provisioning, destroying
            $table->string('claimed_by_job')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_containers');
    }
};
