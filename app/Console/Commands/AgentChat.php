<?php

namespace App\Console\Commands;

use App\DTOs\IncomingMessage;
use App\Models\AgentSession;
use App\Models\User;
use App\Services\Agent\AgentRuntime;
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

    public function handle(AgentRuntime $runtime): int
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

            if ($this->isCommand($input)) {
                $result = $this->handleCommand($input, $user, $sessionKey);
                if ($result === 'quit') {
                    return self::SUCCESS;
                }
                if ($result === 'new') {
                    $sessionKey = 'tui.'.$user->id.'.'.Str::uuid7()->toString();
                    $model = $this->option('model') ?? config('agent.default_model');
                    note('Started new session');
                    note('');
                }

                continue;
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

    protected function isCommand(string $input): bool
    {
        return str_starts_with(trim($input), '/');
    }

    protected function handleCommand(string $input, User $user, string &$sessionKey): ?string
    {
        $command = strtolower(trim($input));

        return match (true) {
            in_array($command, ['/quit', '/exit', '/q']) => 'quit',
            $command === '/new' => 'new',
            $command === '/sessions' => $this->showSessions($user),
            str_starts_with($command, '/resume ') => $this->resumeSession($command, $sessionKey),
            $command === '/info' => $this->showInfo($sessionKey),
            $command === '/help' => $this->showHelp(),
            default => $this->unknownCommand($command),
        };
    }

    protected function showSessions(User $user): null
    {
        $sessions = AgentSession::where('channel', 'tui')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity_at')
            ->take(10)
            ->get(['session_key', 'title', 'last_activity_at']);

        if ($sessions->isEmpty()) {
            note('No TUI sessions found.');

            return null;
        }

        note('Recent TUI sessions:');
        foreach ($sessions as $i => $session) {
            $ago = $session->last_activity_at?->diffForHumans() ?? 'never';
            note("  [{$i}] {$session->title} ({$ago})");
            note("      /resume {$session->session_key}");
        }
        note('');

        return null;
    }

    protected function resumeSession(string $command, string &$sessionKey): null
    {
        $key = trim(Str::after($command, '/resume '));
        $session = AgentSession::where('session_key', $key)->first();

        if (! $session) {
            note("Session not found: {$key}");

            return null;
        }

        $sessionKey = $key;
        $messageCount = $session->messages()->count();
        note("Resumed: {$session->title} ({$messageCount} messages)");
        note('');

        return null;
    }

    protected function showInfo(string $sessionKey): null
    {
        $session = AgentSession::where('session_key', $sessionKey)->first();

        if (! $session) {
            note('Session not yet created (send a message first).');

            return null;
        }

        $messageCount = $session->messages()->count();
        $toolCount = $session->toolExecutions()->count();
        note("Session: {$session->session_key}");
        note("Title: {$session->title}");
        note("Model: {$session->getEffectiveModel()}");
        note("Provider: {$session->getEffectiveProvider()}");
        note("Messages: {$messageCount}");
        note("Tool executions: {$toolCount}");
        note("Trust level: {$session->trust_level}");
        note('');

        return null;
    }

    protected function showHelp(): null
    {
        note('Commands:');
        note('  /quit, /exit, /q  — Exit the chat');
        note('  /new              — Start a new session');
        note('  /sessions         — List recent TUI sessions');
        note('  /resume <key>     — Resume a session by key');
        note('  /info             — Show current session details');
        note('  /help             — Show this help');
        note('');

        return null;
    }

    protected function unknownCommand(string $command): null
    {
        note("Unknown command: {$command} (type /help)");

        return null;
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
