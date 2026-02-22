<?php

namespace App\Console\Commands;

use App\DTOs\IncomingMessage;
use App\Models\AgentSession;
use App\Models\User;
use App\Services\Agent\AgentRuntime;
use App\Services\Agent\IntentRouter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\textarea;

class AgentChat extends Command
{
    protected $signature = 'agent:chat
        {--session= : Resume an existing session by key}
        {--model= : Override the default model}
        {--list : List recent TUI sessions}';

    protected $description = 'Interactive AI chat in the terminal via LaRaClaw agent';

    public function handle(AgentRuntime $runtime, IntentRouter $intentRouter): int
    {
        if ($this->option('list')) {
            return $this->listSessions();
        }

        $user = User::first();
        $sessionKey = $this->option('session') ?? 'tui.'.$user->id.'.'.Str::uuid7()->toString();

        $existing = AgentSession::where('session_key', $sessionKey)->first();
        if ($existing) {
            note("Resuming session: {$existing->title}");
            $messageCount = $existing->messages()->count();
            note("{$messageCount} messages in history");
        } else {
            note('Starting new TUI session');
        }

        $model = $this->option('model') ?? config('agent.default_model');
        note("Model: {$model}");
        note('Type /quit to exit, /sessions to list, /new to start fresh');
        note('');

        while (true) {
            $input = textarea(label: 'You', placeholder: 'Type your message...', required: true);

            // TUI-only commands (not handled by IntentRouter)
            $trimmed = strtolower(trim($input));
            if (in_array($trimmed, ['/quit', '/exit', '/q'])) {
                return self::SUCCESS;
            }
            if ($trimmed === '/sessions') {
                $this->showSessions($user);

                continue;
            }
            if (str_starts_with($trimmed, '/resume ')) {
                $this->resumeSession($trimmed, $sessionKey);

                continue;
            }

            // Delegate slash commands to IntentRouter
            if (str_starts_with(trim($input), '/')) {
                $session = AgentSession::where('session_key', $sessionKey)->first();
                $message = new IncomingMessage(
                    channel: 'tui',
                    sessionKey: $sessionKey,
                    content: $input,
                    sender: 'operator',
                    userId: $user->id,
                    model: $model,
                );

                $intent = $intentRouter->classify($message, $session);

                if ($intent->isShortCircuit()) {
                    // Handle /new specially in TUI
                    if ($intent->commandName === 'new') {
                        $sessionKey = 'tui.'.$user->id.'.'.Str::uuid7()->toString();
                        $model = $this->option('model') ?? config('agent.default_model');
                        note('Started new session');
                        note('');
                    } else {
                        note($intent->response.PHP_EOL);
                    }

                    continue;
                }
            }

            $message = new IncomingMessage(
                channel: 'tui',
                sessionKey: $sessionKey,
                content: $input,
                sender: 'operator',
                userId: $user->id,
                model: $model,
            );

            $response = spin(
                callback: fn () => $runtime->handleMessage($message),
                message: 'Thinking...',
            );

            note($response.PHP_EOL);
        }
    }

    protected function showSessions(User $user): void
    {
        $sessions = AgentSession::where('channel', 'tui')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity_at')
            ->take(10)
            ->get(['session_key', 'title', 'last_activity_at']);

        if ($sessions->isEmpty()) {
            note('No TUI sessions found.');

            return;
        }

        note('Recent TUI sessions:');
        foreach ($sessions as $i => $session) {
            $ago = $session->last_activity_at?->diffForHumans() ?? 'never';
            note("  [{$i}] {$session->title} ({$ago})");
            note("      /resume {$session->session_key}");
        }
        note('');
    }

    protected function resumeSession(string $command, string &$sessionKey): void
    {
        $key = trim(Str::after($command, '/resume '));
        $session = AgentSession::where('session_key', $key)->first();

        if (! $session) {
            note("Session not found: {$key}");

            return;
        }

        $sessionKey = $key;
        $messageCount = $session->messages()->count();
        note("Resumed: {$session->title} ({$messageCount} messages)");
        note('');
    }

    protected function listSessions(): int
    {
        $sessions = AgentSession::where('channel', 'tui')
            ->orderByDesc('last_activity_at')
            ->take(20)
            ->get(['session_key', 'title', 'last_activity_at', 'trust_level']);

        if ($sessions->isEmpty()) {
            $this->info('No TUI sessions found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Session Key', 'Title', 'Last Activity', 'Trust'],
            $sessions->map(fn ($s) => [
                $s->session_key,
                Str::limit($s->title, 40),
                $s->last_activity_at?->diffForHumans() ?? 'never',
                $s->trust_level,
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
