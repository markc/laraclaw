<?php

namespace App\Services\Agent\Commands;

use App\Contracts\CommandHandler;
use App\DTOs\ClassifiedIntent;
use App\DTOs\IncomingMessage;
use App\Enums\IntentType;
use App\Models\AgentSession;

class HelpCommand implements CommandHandler
{
    public function name(): string
    {
        return 'help';
    }

    public function aliases(): array
    {
        return ['?'];
    }

    public function description(): string
    {
        return 'List all available commands';
    }

    public function handle(IncomingMessage $message, array $args, ?AgentSession $session): ClassifiedIntent
    {
        // Resolve lazily to avoid circular dependency (IntentRouter → HelpCommand → IntentRouter)
        $router = app(\App\Services\Agent\IntentRouter::class);
        $handlers = $router->getHandlers();

        $lines = ["Available commands:\n"];
        foreach ($handlers as $handler) {
            $aliases = $handler->aliases();
            $aliasStr = ! empty($aliases) ? ' (aliases: /'.implode(', /', $aliases).')' : '';
            $lines[] = "  /{$handler->name()}{$aliasStr} — {$handler->description()}";
        }

        return new ClassifiedIntent(
            type: IntentType::Command,
            message: $message->content,
            commandName: 'help',
            response: implode("\n", $lines),
        );
    }
}
