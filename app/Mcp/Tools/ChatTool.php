<?php

namespace App\Mcp\Tools;

use App\DTOs\IncomingMessage;
use App\Services\Agent\AgentRuntime;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ChatTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'Send a message to the LaRaClaw AI agent and get a response. Creates a new session if no session_key is provided.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, AgentRuntime $runtime): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Authentication required.');
        }

        $validated = $request->validate([
            'message' => 'required|string|max:32000',
            'session_key' => 'nullable|string|max:255',
        ]);

        $sessionKey = $validated['session_key']
            ?? 'mcp:'.$user->id.':'.Str::uuid7()->toString();

        $incoming = new IncomingMessage(
            channel: 'mcp',
            sessionKey: $sessionKey,
            content: $validated['message'],
            sender: 'operator',
            userId: $user->id,
        );

        $responseText = $runtime->handleMessage($incoming);

        return Response::text(json_encode([
            'session_key' => $sessionKey,
            'response' => $responseText,
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
            'message' => $schema->string()
                ->description('The message to send to the AI agent.')
                ->required(),
            'session_key' => $schema->string()
                ->description('Optional session key to continue an existing conversation. Omit to start a new session.'),
        ];
    }
}
