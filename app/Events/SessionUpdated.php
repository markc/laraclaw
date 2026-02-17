<?php

namespace App\Events;

use App\Models\AgentSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AgentSession $session,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.user.'.$this->session->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->session->id,
            'session_key' => $this->session->session_key,
            'title' => $this->session->title,
            'last_activity_at' => $this->session->last_activity_at?->toISOString(),
            'updated_at' => $this->session->updated_at->toISOString(),
        ];
    }
}
