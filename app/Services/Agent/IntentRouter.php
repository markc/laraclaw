<?php

namespace App\Services\Agent;

use App\Contracts\CommandHandler;
use App\DTOs\ClassifiedIntent;
use App\DTOs\IncomingMessage;
use App\Enums\IntentType;
use App\Models\AgentSession;

class IntentRouter
{
    /**
     * Resolved command handlers keyed by name and aliases.
     *
     * @var array<string, CommandHandler>
     */
    protected array $handlers = [];

    public function __construct()
    {
        $this->registerHandlers();
    }

    /**
     * Classify an incoming message as command, query, or task.
     */
    public function classify(IncomingMessage $message, ?AgentSession $session = null): ClassifiedIntent
    {
        $trimmed = trim($message->content);

        // Check for slash command
        if (str_starts_with($trimmed, '/')) {
            return $this->handleCommand($trimmed, $message, $session);
        }

        // Heuristic classification (no LLM call)
        $type = $this->classifyHeuristic($trimmed);

        return new ClassifiedIntent(
            type: $type,
            message: $message->content,
        );
    }

    /**
     * Get all registered command handlers.
     *
     * @return array<string, CommandHandler>
     */
    public function getHandlers(): array
    {
        // Return unique handlers (filter out aliases)
        $unique = [];
        foreach ($this->handlers as $handler) {
            $unique[$handler->name()] = $handler;
        }

        return $unique;
    }

    protected function handleCommand(string $input, IncomingMessage $message, ?AgentSession $session): ClassifiedIntent
    {
        $parsed = $this->parseCommand($input);
        $name = $parsed['name'];
        $args = $parsed['args'];

        $handler = $this->handlers[$name] ?? null;

        if (! $handler) {
            return new ClassifiedIntent(
                type: IntentType::Command,
                message: $message->content,
                commandName: $name,
                commandArgs: $args,
                response: "Unknown command: /{$name}. Type /help for available commands.",
            );
        }

        return $handler->handle($message, $args, $session);
    }

    /**
     * Parse a slash command string into name and args.
     *
     * @return array{name: string, args: array<string>}
     */
    public function parseCommand(string $input): array
    {
        $trimmed = ltrim(trim($input), '/');
        $parts = preg_split('/\s+/', $trimmed, -1, PREG_SPLIT_NO_EMPTY);

        return [
            'name' => strtolower($parts[0] ?? ''),
            'args' => array_slice($parts, 1),
        ];
    }

    /**
     * Simple heuristic to classify as query vs task (no LLM call).
     */
    protected function classifyHeuristic(string $text): IntentType
    {
        $queryMaxLength = config('intents.query_max_length', 200);
        $taskMinLength = config('intents.task_min_length', 500);

        // Long messages are more likely tasks
        if (mb_strlen($text) >= $taskMinLength) {
            return IntentType::Task;
        }

        // Short messages with question indicators are queries
        if (mb_strlen($text) <= $queryMaxLength) {
            // Question marks, interrogative words at start
            if (str_contains($text, '?') || preg_match('/^\s*(what|how|why|when|where|who|which|is|are|can|do|does|did|will|would|should|could)\b/i', $text)) {
                return IntentType::Query;
            }
        }

        // Code blocks suggest a task
        if (str_contains($text, '```') || str_contains($text, '<?php')) {
            return IntentType::Task;
        }

        // Imperative verbs at start suggest a task
        if (preg_match('/^\s*(create|build|write|implement|add|remove|delete|update|fix|refactor|deploy|generate|make|set up|configure)\b/i', $text)) {
            return IntentType::Task;
        }

        return IntentType::Query;
    }

    protected function registerHandlers(): void
    {
        $commandMap = config('intents.commands', []);

        foreach ($commandMap as $name => $class) {
            /** @var CommandHandler $handler */
            $handler = app($class);
            $this->handlers[$name] = $handler;

            // Register aliases
            foreach ($handler->aliases() as $alias) {
                $this->handlers[$alias] = $handler;
            }
        }
    }
}
