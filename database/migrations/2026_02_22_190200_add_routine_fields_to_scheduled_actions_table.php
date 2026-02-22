<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->string('trigger_type', 20)->default('cron')->after('prompt');
            $table->string('event_class')->nullable()->after('trigger_type');
            $table->json('event_filter')->nullable()->after('event_class');
            $table->string('webhook_token', 64)->nullable()->unique()->after('event_filter');
            $table->json('health_check')->nullable()->after('webhook_token');
            $table->integer('max_retries')->default(1)->after('health_check');
            $table->integer('retry_count')->default(0)->after('max_retries');
            $table->text('last_error')->nullable()->after('retry_count');
            $table->integer('last_duration_ms')->nullable()->after('last_error');
            $table->integer('cooldown_seconds')->default(0)->after('last_duration_ms');
            $table->json('metadata')->nullable()->after('cooldown_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropColumn([
                'trigger_type',
                'event_class',
                'event_filter',
                'webhook_token',
                'health_check',
                'max_retries',
                'retry_count',
                'last_error',
                'last_duration_ms',
                'cooldown_seconds',
                'metadata',
            ]);
        });
    }
};
