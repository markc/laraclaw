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
     * Track pending executions by invocation ID.
     *
     * @var array<string, array{execution: ToolExecution, started_at: float}>
     */
    protected static array $pending = [];

    /**
     * Set the active session before agent invocation.
     */
    public static function setSession(AgentSession $session): void
    {
        static::$activeSession = $session;
    }

    public function handleInvoking(InvokingTool $event): void
    {
        $toolName = method_exists($event->tool, 'name')
            ? $event->tool->name()
            : class_basename($event->tool);

        try {
            $execution = ToolExecution::create([
                'session_id' => static::$activeSession?->id,
                'tool_name' => $toolName,
                'parameters' => $event->arguments,
                'status' => 'running',
            ]);

            static::$pending[$event->toolInvocationId] = [
                'execution' => $execution,
                'started_at' => microtime(true),
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to log tool invocation', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handleInvoked(ToolInvoked $event): void
    {
        $pending = static::$pending[$event->toolInvocationId] ?? null;

        if (! $pending) {
            return;
        }

        $durationMs = (int) ((microtime(true) - $pending['started_at']) * 1000);
        $result = is_string($event->result) ? $event->result : json_encode($event->result);

        try {
            $pending['execution']->update([
                'status' => 'success',
                'result' => ['output' => mb_substr($result, 0, 10000)],
                'duration_ms' => $durationMs,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to update tool execution', [
                'tool' => $pending['execution']->tool_name,
                'error' => $e->getMessage(),
            ]);
        }

        unset(static::$pending[$event->toolInvocationId]);
    }
}
