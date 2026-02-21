<?php

namespace App\Listeners;

use App\Models\AgentSession;
use App\Models\ToolExecution;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolInvoked;

class LogToolExecution
{
    /**
     * The active session for tool execution logging.
     */
    protected static ?AgentSession $activeSession = null;

    /**
     * Track start times by invocation ID.
     *
     * @var array<string, float>
     */
    protected static array $startTimes = [];

    /**
     * Track processed invocation IDs to prevent duplicates.
     *
     * @var array<string, true>
     */
    protected static array $processed = [];

    /**
     * Set the active session before agent invocation.
     */
    public static function setSession(AgentSession $session): void
    {
        static::$activeSession = $session;
    }

    public function handleInvoking(InvokingTool $event): void
    {
        static::$startTimes[$event->toolInvocationId] = microtime(true);
    }

    public function handleInvoked(ToolInvoked $event): void
    {
        if (isset(static::$processed[$event->toolInvocationId])) {
            return;
        }

        static::$processed[$event->toolInvocationId] = true;

        $startedAt = static::$startTimes[$event->toolInvocationId] ?? null;
        $durationMs = $startedAt
            ? (int) ((microtime(true) - $startedAt) * 1000)
            : null;

        $toolName = method_exists($event->tool, 'name')
            ? $event->tool->name()
            : class_basename($event->tool);
        $result = is_string($event->result) ? $event->result : json_encode($event->result);

        try {
            ToolExecution::create([
                'session_id' => static::$activeSession?->id,
                'tool_name' => $toolName,
                'parameters' => $event->arguments,
                'status' => 'success',
                'result' => ['output' => mb_substr($result, 0, 10000)],
                'duration_ms' => $durationMs,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log tool execution', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);
        }

        unset(static::$startTimes[$event->toolInvocationId]);
    }
}
