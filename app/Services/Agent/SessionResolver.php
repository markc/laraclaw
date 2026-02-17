<?php

namespace App\Services\Agent;

use App\DTOs\IncomingMessage;
use App\Models\Agent;
use App\Models\AgentSession;

class SessionResolver
{
    public function resolve(IncomingMessage $message): AgentSession
    {
        return AgentSession::firstOrCreate(
            ['session_key' => $message->sessionKey],
            [
                'agent_id' => $this->resolveAgent($message)->id,
                'user_id' => $message->userId,
                'channel' => $message->channel,
                'trust_level' => config("channels.{$message->channel}.trust_level", 'standard'),
                'model' => $message->model,
                'provider' => $message->provider,
                'system_prompt' => $message->systemPrompt,
            ]
        );
    }

    public function resolveOrFail(string $sessionKey): AgentSession
    {
        return AgentSession::where('session_key', $sessionKey)->firstOrFail();
    }

    protected function resolveAgent(IncomingMessage $message): Agent
    {
        return Agent::where('is_default', true)->first()
            ?? Agent::firstOrCreate(
                ['slug' => 'default'],
                [
                    'name' => 'Default Agent',
                    'slug' => 'default',
                    'model' => config('agent.default_model'),
                    'provider' => config('agent.default_provider'),
                    'is_default' => true,
                ]
            );
    }
}
