<?php

namespace App\Jobs;

use App\DTOs\IncomingMessage;
use App\Services\Agent\AgentRuntime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

class ProcessChatMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        public IncomingMessage $message,
    ) {}

    public function handle(AgentRuntime $runtime): void
    {
        $runtime->streamAndBroadcast($this->message);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessChatMessage failed', [
            'session_key' => $this->message->sessionKey,
            'error' => $e->getMessage(),
        ]);

        // Broadcast error to the session channel (colons → dots for Pusher)
        $channelKey = str_replace(':', '.', $this->message->sessionKey);
        Broadcast::private('chat.session.'.$channelKey)
            ->as('error')
            ->with([
                'type' => 'error',
                'error' => 'Failed to process message. Please try again.',
                'timestamp' => now()->timestamp,
            ])
            ->sendNow();
    }
}
