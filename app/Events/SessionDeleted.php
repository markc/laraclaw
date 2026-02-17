<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $sessionId,
        public int $userId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.user.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->sessionId,
        ];
    }
}
