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
    ) {}

    public function handle(AgentRuntime $runtime): void
    {
        $sessionKey = $this->action->session_key
            ?? 'scheduled.'.$this->action->user_id.'.'.Str::uuid7();

        $message = new IncomingMessage(
            channel: 'scheduled',
            sessionKey: $sessionKey,
            content: $this->action->prompt,
            userId: $this->action->user_id,
        );

        $runtime->handleMessage($message);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessScheduledAction failed', [
            'action_id' => $this->action->id,
            'action_name' => $this->action->name,
            'error' => $e->getMessage(),
        ]);
    }
}
