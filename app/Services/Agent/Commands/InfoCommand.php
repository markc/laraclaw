<?php

namespace App\Services\Agent\Commands;

use App\Contracts\CommandHandler;
use App\DTOs\ClassifiedIntent;
use App\DTOs\IncomingMessage;
use App\Enums\IntentType;
use App\Models\AgentSession;

class InfoCommand implements CommandHandler
{
    public function name(): string
    {
        return 'info';
    }

    public function aliases(): array
    {
        return ['status'];
    }

    public function description(): string
    {
        return 'Show current session details';
    }

    public function handle(IncomingMessage $message, array $args, ?AgentSession $session): ClassifiedIntent
    {
        if (! $session) {
            return new ClassifiedIntent(
                type: IntentType::Command,
                message: $message->content,
                commandName: 'info',
                response: 'No active session. Send a message to start one.',
            );
        }

        $messageCount = $session->messages()->count();
        $toolCount = $session->toolExecutions()->count();

        $lines = [
            "Session: {$session->session_key}",
            "Title: {$session->title}",
            "Model: {$session->getEffectiveModel()}",
            "Provider: {$session->getEffectiveProvider()}",
            "Channel: {$session->channel}",
            "Messages: {$messageCount}",
            "Tool executions: {$toolCount}",
            "Trust level: {$session->trust_level}",
        ];

        return new ClassifiedIntent(
            type: IntentType::Command,
            message: $message->content,
            commandName: 'info',
            response: implode("\n", $lines),
        );
    }
}
