<?php

namespace App\Services\Routines;

use App\Models\AgentSession;
use Illuminate\Support\Facades\DB;

class HealthMonitor
{
    /**
     * Check for jobs stuck in reserved state.
     *
     * @return array{type: string, count: int, details: array}
     */
    public function checkStuckJobs(int $thresholdMinutes = 30): array
    {
        $stuckJobs = DB::table('jobs')
            ->where('reserved_at', '<', now()->subMinutes($thresholdMinutes)->timestamp)
            ->whereNotNull('reserved_at')
            ->get(['id', 'queue', 'payload', 'reserved_at']);

        return [
            'type' => 'stuck_jobs',
            'count' => $stuckJobs->count(),
            'details' => $stuckJobs->map(fn ($job) => [
                'id' => $job->id,
                'queue' => $job->queue,
                'reserved_at' => date('Y-m-d H:i:s', $job->reserved_at),
            ])->all(),
        ];
    }

    /**
     * Check for recently failed jobs.
     *
     * @return array{type: string, count: int, details: array}
     */
    public function checkFailedJobs(int $sinceMinutes = 60): array
    {
        $failedJobs = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subMinutes($sinceMinutes))
            ->get(['id', 'queue', 'payload', 'exception', 'failed_at']);

        return [
            'type' => 'failed_jobs',
            'count' => $failedJobs->count(),
            'details' => $failedJobs->map(fn ($job) => [
                'id' => $job->id,
                'queue' => $job->queue,
                'failed_at' => $job->failed_at,
                'exception' => mb_substr($job->exception, 0, 200),
            ])->all(),
        ];
    }

    /**
     * Check for cron routines that have missed their schedule.
     *
     * @return array{type: string, count: int, details: array}
     */
    public function checkMissedHeartbeats(): array
    {
        $missed = DB::table('scheduled_actions')
            ->where('trigger_type', 'cron')
            ->where('is_enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<', now()->subMinutes(5))
            ->get(['id', 'name', 'schedule', 'next_run_at', 'last_run_at']);

        return [
            'type' => 'missed_heartbeats',
            'count' => $missed->count(),
            'details' => $missed->map(fn ($action) => [
                'id' => $action->id,
                'name' => $action->name,
                'schedule' => $action->schedule,
                'next_run_at' => $action->next_run_at,
                'last_run_at' => $action->last_run_at,
            ])->all(),
        ];
    }

    /**
     * Check for sessions with recent activity but no recent assistant response.
     *
     * @return array{type: string, count: int, details: array}
     */
    public function checkStaleSessions(int $thresholdMinutes = 15): array
    {
        $staleSessions = AgentSession::query()
            ->where('last_activity_at', '>=', now()->subMinutes($thresholdMinutes * 2))
            ->where('last_activity_at', '<=', now()->subMinutes($thresholdMinutes))
            ->whereHas('messages', function ($q) use ($thresholdMinutes) {
                $q->where('role', 'user')
                    ->where('created_at', '>=', now()->subMinutes($thresholdMinutes));
            })
            ->whereDoesntHave('messages', function ($q) use ($thresholdMinutes) {
                $q->where('role', 'assistant')
                    ->where('created_at', '>=', now()->subMinutes($thresholdMinutes));
            })
            ->get(['id', 'session_key', 'title', 'last_activity_at']);

        return [
            'type' => 'stale_sessions',
            'count' => $staleSessions->count(),
            'details' => $staleSessions->map(fn ($s) => [
                'id' => $s->id,
                'session_key' => $s->session_key,
                'title' => $s->title,
                'last_activity_at' => $s->last_activity_at?->toISOString(),
            ])->all(),
        ];
    }

    /**
     * Run all health checks.
     *
     * @return array<array{type: string, count: int, details: array}>
     */
    public function runAll(): array
    {
        return [
            $this->checkStuckJobs(),
            $this->checkFailedJobs(),
            $this->checkMissedHeartbeats(),
            $this->checkStaleSessions(),
        ];
    }
}
