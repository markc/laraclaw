<?php

namespace App\Services\Agent;

use App\DTOs\IncomingMessage;
use App\Models\AgentSession;

class ContextAssembler
{
    public function __construct(
        protected SystemPromptBuilder $promptBuilder,
    ) {}

    /**
     * Build the full context array for the AI provider.
     *
     * @return array{system: string, messages: array}
     */
    public function build(AgentSession $session, IncomingMessage $message): array
    {
        $systemPrompt = $this->promptBuilder->build($session);

        $messages = [];

        // Add compacted summary if exists
        if ($session->compacted_summary) {
            $messages[] = [
                'role' => 'system',
                'content' => "Previous conversation summary:\n".$session->compacted_summary,
            ];
        }

        // Add conversation history
        foreach ($session->messages()->limit(config('agent.max_conversation_messages', 100))->get() as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];
        }

        // Add current message
        $messages[] = [
            'role' => 'user',
            'content' => $message->content,
        ];

        return [
            'system' => $systemPrompt,
            'messages' => $messages,
        ];
    }
}
