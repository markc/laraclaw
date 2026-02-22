<?php

namespace App\Listeners;

use App\Jobs\ProcessScheduledAction;
use App\Models\ScheduledAction;
use App\Services\Routines\EventFilterEvaluator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RoutineEventDispatcher
{
    /**
     * Re-entrancy guard to prevent infinite recursion from framework events (e.g. cache).
     */
    protected static bool $handling = false;

    public function __construct(
        protected EventFilterEvaluator $evaluator,
    ) {}

    /**
     * Handle any dispatched event (registered as wildcard listener).
     */
    public function handle(string $eventName, array $data): void
    {
        // Guard: only handle App\ events, skip framework/vendor events
        if (! str_starts_with($eventName, 'App\\')) {
            return;
        }

        // Guard: prevent re-entrant calls (Cache::remember fires events too)
        if (static::$handling) {
            return;
        }

        static::$handling = true;

        try {
            $this->dispatch($eventName, $data);
        } finally {
            static::$handling = false;
        }
    }

    protected function dispatch(string $eventName, array $data): void
    {
        // Short-circuit if no event-type routines watch this event class
        $watchedClasses = $this->getWatchedEventClasses();
        if (! in_array($eventName, $watchedClasses)) {
            return;
        }

        $routines = ScheduledAction::query()
            ->event()
            ->where('is_enabled', true)
            ->where('event_class', $eventName)
            ->get();

        foreach ($routines as $routine) {
            // Evaluate event filter
            $payload = $this->extractPayload($data);
            if (! empty($routine->event_filter) && ! $this->evaluator->evaluate($routine->event_filter, $payload)) {
                continue;
            }

            // Check cooldown
            if (! $routine->isCooldownElapsed()) {
                continue;
            }

            // Dispatch with event context
            ProcessScheduledAction::dispatch($routine, [
                'trigger' => 'event',
                'event_class' => $eventName,
                'payload' => $payload,
            ]);

            $routine->update(['last_run_at' => now()]);

            Log::debug('RoutineEventDispatcher: dispatched routine', [
                'routine_id' => $routine->id,
                'event' => $eventName,
            ]);
        }
    }

    /**
     * Get cached list of event classes with active routines.
     *
     * @return array<string>
     */
    protected function getWatchedEventClasses(): array
    {
        return Cache::remember('routine_watched_events', 60, function () {
            return ScheduledAction::query()
                ->event()
                ->where('is_enabled', true)
                ->whereNotNull('event_class')
                ->distinct()
                ->pluck('event_class')
                ->all();
        });
    }

    /**
     * Extract a serializable payload from event data.
     */
    protected function extractPayload(array $data): array
    {
        $payload = [];

        foreach ($data as $key => $value) {
            if (is_object($value)) {
                // Convert Eloquent models to array
                if (method_exists($value, 'toArray')) {
                    $payload[$key] = $value->toArray();
                } else {
                    $payload[$key] = (array) $value;
                }
            } else {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
