<?php

namespace App\Services\Agent;

use App\DTOs\IncomingMessage;
use App\Models\AgentMessage;
use App\Models\AgentSession;
use App\Services\Tools\BuiltIn\CurrentDateTimeTool;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Responses\StreamableAgentResponse;

class AgentRuntime
{
    public function __construct(
        protected SessionResolver $sessionResolver,
        protected ContextAssembler $contextAssembler,
    ) {}

    /**
     * Handle an incoming message synchronously (for TUI/email channels).
     */
    public function handleMessage(IncomingMessage $message): string
    {
        $session = $this->sessionResolver->resolve($message);

        // Save user message
        $this->saveMessage($session, 'user', $message->content, [
            'channel' => $message->channel,
            'sender' => $message->sender,
        ]);

        $context = $this->contextAssembler->build($session, $message);
        $agent = $this->buildAgent($context, $session);

        try {
            $response = $agent->prompt(
                prompt: $message->content,
                provider: $session->getEffectiveProvider(),
                model: $session->getEffectiveModel(),
            );

            $responseText = $response->text;
            $usage = [
                'input_tokens' => $response->usage?->inputTokens ?? 0,
                'output_tokens' => $response->usage?->outputTokens ?? 0,
            ];
        } catch (\Throwable $e) {
            Log::error('AgentRuntime: AI invocation failed', [
                'session' => $session->session_key,
                'error' => $e->getMessage(),
            ]);
            $responseText = "I'm sorry, I encountered an error processing your request.";
            $usage = [];
        }

        $this->saveMessage($session, 'assistant', $responseText, [
            'provider' => $session->getEffectiveProvider(),
            'model' => $session->getEffectiveModel(),
        ], $usage);

        $session->update(['last_activity_at' => now()]);
        $this->maybeGenerateTitle($session);

        return $responseText;
    }

    /**
     * Stream a response for the web chat channel.
     */
    public function streamMessage(IncomingMessage $message): StreamableAgentResponse
    {
        $session = $this->sessionResolver->resolve($message);

        // Save user message
        $this->saveMessage($session, 'user', $message->content, [
            'channel' => $message->channel,
            'sender' => $message->sender,
        ]);

        $context = $this->contextAssembler->build($session, $message);
        $agent = $this->buildAgent($context, $session);

        $stream = $agent->stream(
            prompt: $message->content,
            provider: $session->getEffectiveProvider(),
            model: $session->getEffectiveModel(),
        );

        // Save response after streaming completes
        $stream->then(function ($response) use ($session) {
            $this->saveMessage($session, 'assistant', $response->text ?? '', [
                'provider' => $session->getEffectiveProvider(),
                'model' => $session->getEffectiveModel(),
            ], [
                'input_tokens' => $response->usage?->inputTokens ?? 0,
                'output_tokens' => $response->usage?->outputTokens ?? 0,
            ]);

            $session->update(['last_activity_at' => now()]);
            $this->maybeGenerateTitle($session);
        });

        return $stream;
    }

    /**
     * Build an AnonymousAgent from context.
     */
    protected function buildAgent(array $context, AgentSession $session): AnonymousAgent
    {
        // Convert history to SDK Message objects
        $messages = collect($context['messages'])
            ->filter(fn ($m) => $m['role'] !== 'user' || $m !== end($context['messages']))
            ->map(fn ($m) => new Message($m['role'], $m['content']))
            ->all();

        // Remove the last user message from messages (it goes via prompt())
        if (! empty($messages)) {
            $last = end($messages);
            if ($last->role === 'user') {
                array_pop($messages);
            }
        }

        return new AnonymousAgent(
            instructions: $context['system'],
            messages: $messages,
            tools: [
                new CurrentDateTimeTool(),
            ],
        );
    }

    protected function saveMessage(
        AgentSession $session,
        string $role,
        string $content,
        array $meta = [],
        array $usage = [],
    ): AgentMessage {
        return $session->messages()->create([
            'role' => $role,
            'content' => $content,
            'meta' => $meta,
            'usage' => $usage,
        ]);
    }

    protected function maybeGenerateTitle(AgentSession $session): void
    {
        if ($session->title !== 'New Chat') {
            return;
        }

        $firstMessage = $session->messages()->where('role', 'user')->first();
        if (! $firstMessage) {
            return;
        }

        $title = mb_substr($firstMessage->content, 0, 60);
        if (mb_strlen($firstMessage->content) > 60) {
            $title .= '...';
        }

        $session->update(['title' => $title]);
    }
}
