<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledAction;
use App\Models\ScheduledAction;
use Cron\CronExpression;
use Illuminate\Console\Command;

class AgentHeartbeat extends Command
{
    protected $signature = 'agent:heartbeat';

    protected $description = 'Evaluate and dispatch due scheduled actions (cron-type only)';

    public function handle(): int
    {
        $actions = ScheduledAction::query()
            ->cron()
            ->where('is_enabled', true)
            ->where(function ($query) {
                $query->where('next_run_at', '<=', now())
                    ->orWhereNull('next_run_at');
            })
            ->get();

        $dispatched = 0;

        foreach ($actions as $action) {
            ProcessScheduledAction::dispatch($action);

            $cron = new CronExpression($action->schedule);
            $action->update([
                'last_run_at' => now(),
                'next_run_at' => $cron->getNextRunDate(),
            ]);

            $dispatched++;
        }

        $this->info("Heartbeat: dispatched {$dispatched} actions.");

        return self::SUCCESS;
    }
}
