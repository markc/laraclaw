<?php

namespace App\Jobs;

use App\DTOs\IncomingMessage;
use App\Models\ScheduledAction;
use App\Services\Agent\AgentRuntime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessScheduledAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public ScheduledAction $action,
        public array $triggerContext = [],
    ) {}

    public function handle(AgentRuntime $runtime): void
    {
        $startTime = microtime(true);

        $sessionKey = $this->action->session_key
            ?? 'scheduled.'.$this->action->user_id.'.'.Str::uuid7();

        // Enrich prompt with trigger context if present
        $prompt = $this->action->prompt;
        if (! empty($this->triggerContext)) {
            $contextJson = json_encode($this->triggerContext, JSON_PRETTY_PRINT);
            $prompt .= "\n\n---\nTrigger context:\n```json\n{$contextJson}\n```";
        }

        $message = new IncomingMessage(
            channel: 'scheduled',
            sessionKey: $sessionKey,
            content: $prompt,
            userId: $this->action->user_id,
        );

        $runtime->handleMessage($message);

        // Track execution duration
        $durationMs = (int) ((microtime(true) - $startTime) * 1000);
        $this->action->update([
            'last_duration_ms' => $durationMs,
            'last_error' => null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $this->action->increment('retry_count');
        $this->action->update([
            'last_error' => mb_substr($e->getMessage(), 0, 1000),
        ]);

        // Disable if max retries exceeded
        if ($this->action->retry_count >= $this->action->max_retries) {
            $this->action->update(['is_enabled' => false]);

            Log::warning('ProcessScheduledAction: disabled after max retries', [
                'action_id' => $this->action->id,
                'action_name' => $this->action->name,
                'retry_count' => $this->action->retry_count,
            ]);
        }

        Log::error('ProcessScheduledAction failed', [
            'action_id' => $this->action->id,
            'action_name' => $this->action->name,
            'error' => $e->getMessage(),
        ]);
    }
}
