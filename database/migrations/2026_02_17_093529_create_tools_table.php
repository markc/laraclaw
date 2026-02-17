<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('class_name');
            $table->text('description');
            $table->json('parameters_schema')->default('{}');
            $table->json('default_policy')->default('{}');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_builtin')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
