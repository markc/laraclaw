<?php

namespace App\Providers;

use App\Listeners\LogToolExecution;
use App\Listeners\RoutineEventDispatcher;
use App\Models\AgentSession;
use App\Policies\AgentSessionPolicy;
use App\Services\Agent\IntentRouter;
use App\Services\Email\ImapService;
use App\Services\Email\JmapService;
use App\Services\Email\MailboxService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\ToolInvoked;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MailboxService::class, function () {
            return config('channels.email.protocol') === 'imap'
                ? new ImapService
                : new JmapService;
        });

        $this->app->singleton(IntentRouter::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(AgentSession::class, AgentSessionPolicy::class);

        Event::listen(InvokingTool::class, [LogToolExecution::class, 'handleInvoking']);
        Event::listen(ToolInvoked::class, [LogToolExecution::class, 'handleInvoked']);

        // Wildcard listener for routine event triggers
        Event::listen('*', [RoutineEventDispatcher::class, 'handle']);
    }
}
