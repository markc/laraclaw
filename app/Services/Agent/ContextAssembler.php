<?php

namespace App\Services\Agent;

use App\DTOs\IncomingMessage;
use App\Models\AgentSession;
use App\Services\Memory\MemorySearchService;
use Illuminate\Support\Facades\Log;

class ContextAssembler
{
    public function __construct(
        protected SystemPromptBuilder $promptBuilder,
        protected MemorySearchService $memorySearchService,
    ) {}

    /**
     * Build the full context array for the AI provider.
     *
     * @return array{system: string, messages: array}
     */
    public function build(AgentSession $session, IncomingMessage $message): array
    {
        $systemPrompt = $this->promptBuilder->build($session);

        // Append relevant memories to system prompt
        $memoryContext = $this->buildMemoryContext($session, $message);
        if ($memoryContext) {
            $systemPrompt .= "\n\n---\n\n".$memoryContext;
        }

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

    /**
     * Search for relevant memories and format as context section.
     */
    protected function buildMemoryContext(AgentSession $session, IncomingMessage $message): ?string
    {
        if (! config('memory.search.enabled', true)) {
            return null;
        }

        try {
            $memories = $this->memorySearchService->search(
                query: $message->content,
                agentId: $session->agent_id,
                limit: config('memory.search.default_limit', 10),
            );

            if ($memories->isEmpty()) {
                return null;
            }

            $formatted = $memories->map(function ($memory) {
                $type = $memory->memory_type;
                $date = $memory->created_at->format('Y-m-d');

                return "- [{$type}, {$date}] {$memory->content}";
            })->implode("\n");

            return "## Relevant Memories\n\n{$formatted}";
        } catch (\Throwable $e) {
            Log::warning('ContextAssembler: memory search failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
