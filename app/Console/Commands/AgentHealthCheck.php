<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledAction;
use App\Models\ScheduledAction;
use App\Services\Routines\HealthMonitor;
use Illuminate\Console\Command;

class AgentHealthCheck extends Command
{
    protected $signature = 'agent:health-check';

    protected $description = 'Run health checks and dispatch matching health-type routines';

    public function handle(HealthMonitor $monitor): int
    {
        $results = $monitor->runAll();

        $issues = collect($results)->filter(fn ($r) => $r['count'] > 0);

        if ($issues->isEmpty()) {
            $this->info('Health check: all clear.');

            return self::SUCCESS;
        }

        // Find health-type routines that match detected issues
        $healthRoutines = ScheduledAction::query()
            ->health()
            ->where('is_enabled', true)
            ->get();

        $dispatched = 0;

        foreach ($healthRoutines as $routine) {
            $checkConfig = $routine->health_check ?? [];
            $checkType = $checkConfig['type'] ?? null;

            // Find matching result
            $matchingResult = collect($results)->first(fn ($r) => $r['type'] === $checkType);
            if (! $matchingResult || $matchingResult['count'] === 0) {
                continue;
            }

            if (! $routine->isCooldownElapsed()) {
                continue;
            }

            ProcessScheduledAction::dispatch($routine, [
                'trigger' => 'health',
                'health_check' => $matchingResult,
            ]);

            $routine->update(['last_run_at' => now()]);
            $dispatched++;
        }

        $issueTypes = $issues->pluck('type')->implode(', ');
        $this->warn("Health check: {$issues->count()} issue(s) found ({$issueTypes}), dispatched {$dispatched} routine(s).");

        return self::SUCCESS;
    }
}
