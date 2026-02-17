<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

class ChatPrompt extends Prompt
{
    /**
     * The prompt's description.
     */
    protected string $description = 'Start a conversation with the LaRaClaw AI agent on a given topic.';

    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): array
    {
        $topic = $request->string('topic');
        $context = $request->string('context');

        $system = 'You are interacting with LaRaClaw, a self-hosted AI agent platform. '
            .'Use the chat tool to send messages and get responses.';

        $userMessage = "Let's discuss: {$topic}";

        if ($context !== '') {
            $userMessage .= "\n\nAdditional context: {$context}";
        }

        return [
            Response::text($system)->asAssistant(),
            Response::text($userMessage),
        ];
    }

    /**
     * Get the prompt's arguments.
     *
     * @return array<int, \Laravel\Mcp\Server\Prompts\Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'topic',
                description: 'The topic to discuss with the AI agent.',
                required: true,
            ),
            new Argument(
                name: 'context',
                description: 'Optional additional context or background information.',
                required: false,
            ),
        ];
    }
}
