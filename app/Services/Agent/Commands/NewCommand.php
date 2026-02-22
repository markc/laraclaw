<?php

namespace App\Services\Agent\Commands;

use App\Contracts\CommandHandler;
use App\DTOs\ClassifiedIntent;
use App\DTOs\IncomingMessage;
use App\Enums\IntentType;
use App\Models\AgentSession;

class NewCommand implements CommandHandler
{
    public function name(): string
    {
        return 'new';
    }

    public function aliases(): array
    {
        return ['reset'];
    }

    public function description(): string
    {
        return 'Start a new session';
    }

    public function handle(IncomingMessage $message, array $args, ?AgentSession $session): ClassifiedIntent
    {
        return new ClassifiedIntent(
            type: IntentType::Command,
            message: $message->content,
            commandName: 'new',
            response: 'Starting new session.',
            metadata: ['action' => 'new_session'],
        );
    }
}
