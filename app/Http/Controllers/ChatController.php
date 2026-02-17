<?php

namespace App\Http\Controllers;

use App\DTOs\IncomingMessage;
use App\Models\AgentSession;
use App\Services\Agent\AgentRuntime;
use App\Services\Agent\ModelRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ChatController extends Controller
{
    public function __construct(
        protected AgentRuntime $runtime,
        protected ModelRegistry $modelRegistry,
    ) {}

    /**
     * GET /chat — New chat page.
     */
    public function index()
    {
        return Inertia::render('Chat', [
            'session' => null,
            'availableModels' => $this->modelRegistry->getAvailableModels(),
        ]);
    }

    /**
     * GET /chat/{agentSession} — Show existing chat.
     */
    public function show(AgentSession $agentSession)
    {
        $this->authorize('view', $agentSession);

        $agentSession->load('messages');

        return Inertia::render('Chat', [
            'session' => $agentSession,
            'availableModels' => $this->modelRegistry->getAvailableModels(),
        ]);
    }

    /**
     * POST /chat/stream — Streaming chat endpoint.
     */
    public function stream(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:32000',
            'session_key' => 'nullable|string',
            'model' => 'nullable|string',
            'provider' => 'nullable|string',
            'system_prompt' => 'nullable|string',
        ]);

        $user = $request->user();
        $sessionKey = $request->input('session_key')
            ?? 'web:' . $user->id . ':' . Str::uuid7();

        $provider = $request->input('provider');
        $model = $request->input('model');

        // Auto-resolve provider from model if not provided
        if ($model && ! $provider) {
            $provider = $this->modelRegistry->resolveProvider($model);
        }

        $message = new IncomingMessage(
            channel: 'web',
            sessionKey: $sessionKey,
            content: $request->input('message'),
            sender: $user->name,
            userId: $user->id,
            provider: $provider,
            model: $model,
            systemPrompt: $request->input('system_prompt'),
        );

        $stream = $this->runtime->streamMessage($message);

        // Include session key in response headers for the frontend
        return $stream
            ->usingVercelDataProtocol()
            ->toResponse($request)
            ->withHeaders([
                'X-Session-Key' => $sessionKey,
            ]);
    }

    /**
     * DELETE /chat/{agentSession} — Delete a chat session.
     */
    public function destroy(AgentSession $agentSession)
    {
        $this->authorize('delete', $agentSession);

        $agentSession->delete();

        return redirect()->route('chat.index');
    }

    /**
     * PATCH /chat/{agentSession} — Rename a chat session.
     */
    public function update(Request $request, AgentSession $agentSession)
    {
        $this->authorize('update', $agentSession);

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $agentSession->update(['title' => $request->input('title')]);

        return back();
    }
}
