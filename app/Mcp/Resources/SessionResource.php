<?php

namespace App\Mcp\Resources;

use App\Models\AgentSession;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

class SessionResource extends Resource implements HasUriTemplate
{
    /**
     * The resource's description.
     */
    protected string $description = 'Read a conversation session by its session key, including metadata and recent messages.';

    /**
     * The resource's MIME type.
     */
    protected string $mimeType = 'application/json';

    /**
     * Get the URI template for this resource.
     */
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('laraclaw://sessions/{sessionKey}');
    }

    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        $user = $request->user();
        $sessionKey = $request->get('sessionKey');

        if (! $user || ! $sessionKey) {
            return Response::error('Authentication required.');
        }

        $session = AgentSession::where('session_key', $sessionKey)
            ->where('user_id', $user->id)
            ->first();

        if (! $session) {
            return Response::error('Session not found or access denied.');
        }

        $messages = $session->messages()
            ->latest()
            ->limit(50)
            ->get(['role', 'content', 'created_at'])
            ->reverse()
            ->values();

        return Response::text(json_encode([
            'session_key' => $session->session_key,
            'title' => $session->title,
            'channel' => $session->channel,
            'created_at' => $session->created_at,
            'last_activity_at' => $session->last_activity_at,
            'messages' => $messages,
        ], JSON_THROW_ON_ERROR));
    }
}
