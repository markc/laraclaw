<?php

namespace App\Http\Middleware;

use App\Models\AgentSession;
use App\Services\Agent\ModelRegistry;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarConversations' => fn () => $request->user()
                ? AgentSession::where('user_id', $request->user()->id)
                    ->orderByDesc('last_activity_at')
                    ->limit(50)
                    ->get(['id', 'session_key', 'title', 'model', 'provider', 'last_activity_at', 'updated_at'])
                : [],
            'sidebarStats' => fn () => $request->user()
                ? $this->buildStats($request->user()->id)
                : null,
            'availableModels' => fn () => app(ModelRegistry::class)->getAvailableModels(),
        ];
    }

    protected function buildStats(int $userId): array
    {
        $sessions = AgentSession::where('user_id', $userId);

        return [
            'conversations' => $sessions->count(),
            'messages' => \App\Models\AgentMessage::whereIn(
                'session_id',
                $sessions->pluck('id')
            )->count(),
        ];
    }
}
