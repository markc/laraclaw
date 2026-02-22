<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessScheduledAction;
use App\Models\ScheduledAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookRoutineController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $routine = ScheduledAction::query()
            ->webhook()
            ->where('webhook_token', $token)
            ->where('is_enabled', true)
            ->first();

        if (! $routine) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if (! $routine->isCooldownElapsed()) {
            return response()->json(['error' => 'Cooldown active'], 429);
        }

        ProcessScheduledAction::dispatch($routine, [
            'trigger' => 'webhook',
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);

        $routine->update(['last_run_at' => now()]);

        return response()->json(['status' => 'accepted'], 202);
    }
}
