<?php

namespace App\Mcp\Tools;

use App\Models\AgentSession;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListSessionsTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = 'List your conversation sessions, ordered by most recent activity.';

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
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $limit = $validated['limit'] ?? 20;

        $sessions = AgentSession::where('user_id', $user->id)
            ->orderByDesc('last_activity_at')
            ->limit($limit)
            ->get(['session_key', 'title', 'channel', 'last_activity_at', 'created_at']);

        return Response::text(json_encode($sessions, JSON_THROW_ON_ERROR));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('Maximum number of sessions to return (default: 20, max: 100).'),
        ];
    }
}
