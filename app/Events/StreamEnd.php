<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class StreamEnd implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        protected Channel $channel,
        protected string $type,
        protected array $data = [],
    ) {}

    public function broadcastOn(): array
    {
        return [$this->channel];
    }

    public function broadcastAs(): string
    {
        return $this->type;
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }

    /**
     * Broadcast a complete short-circuit response as stream_start + text_delta + stream_end.
     */
    public static function synthetic(Channel $channel, string $text): void
    {
        broadcast(new self($channel, 'stream_start'))->toOthers();
        broadcast(new self($channel, 'text_delta', ['delta' => $text]))->toOthers();
        broadcast(new self($channel, 'stream_end'))->toOthers();
    }
}
