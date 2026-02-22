<?php

namespace App\Services\Agent\Commands;

use App\Contracts\CommandHandler;
use App\DTOs\ClassifiedIntent;
use App\DTOs\IncomingMessage;
use App\Enums\IntentType;
use App\Events\SessionUpdated;
use App\Models\AgentSession;

class RenameCommand implements CommandHandler
{
    public function name(): string
    {
        return 'rename';
    }

    public function aliases(): array
    {
        return ['title'];
    }

    public function description(): string
    {
        return 'Rename the current session: /rename <new title>';
    }

    public function handle(IncomingMessage $message, array $args, ?AgentSession $session): ClassifiedIntent
    {
        if (empty($args)) {
            return new ClassifiedIntent(
                type: IntentType::Command,
                message: $message->content,
                commandName: 'rename',
                response: 'Usage: /rename <new title>',
            );
        }

        if (! $session) {
            return new ClassifiedIntent(
                type: IntentType::Command,
                message: $message->content,
                commandName: 'rename',
                commandArgs: $args,
                response: 'No active session to rename.',
            );
        }

        $title = implode(' ', $args);
        $title = mb_substr($title, 0, 100);

        $session->update(['title' => $title]);
        event(new SessionUpdated($session->fresh()));

        return new ClassifiedIntent(
            type: IntentType::Command,
            message: $message->content,
            commandName: 'rename',
            commandArgs: $args,
            response: "Session renamed to: {$title}",
        );
    }
}
