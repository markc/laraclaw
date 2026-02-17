<?php

namespace App\Mcp\Tools;

use App\Models\AgentSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ReadSessionTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Read messages from a conversation session. Only returns sessions you own.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'session_key' => 'required|string|max:255',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $session = AgentSession::where('session_key', $validated['session_key'])
            ->where('user_id', $user->id)
            ->first();

        if (! $session) {
            return Response::error('Session not found or access denied.');
        }

        $limit = $validated['limit'] ?? 50;

        $messages = $session->messages()
            ->latest()
            ->limit($limit)
            ->get(['role', 'content', 'created_at'])
            ->reverse()
            ->values();

        return Response::text(json_encode([
            'session_key' => $session->session_key,
            'title' => $session->title,
            'channel' => $session->channel,
            'messages' => $messages,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'session_key' => $schema->string()
                ->description('The session key to read messages from.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum number of messages to return (default: 50, max: 200). Returns most recent messages.'),
        ];
    }
}
