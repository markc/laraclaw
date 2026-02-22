<?php

namespace App\Services\Security;

use App\DTOs\SanitizeResult;
use App\Models\InjectionDetection;
use Illuminate\Support\Facades\Log;

class InjectionAuditLog
{
    /**
     * Log an injection detection to both Laravel log and database.
     */
    public function log(SanitizeResult $result, ?int $sessionId = null): void
    {
        if (! $result->injectionDetected) {
            return;
        }

        if (! config('security.sanitizer.log_detections', true)) {
            return;
        }

        // Log to Laravel log
        Log::warning('Prompt injection detected', [
            'source' => $result->source->value,
            'policy' => $result->policyApplied->value,
            'patterns' => $result->detections,
            'session_id' => $sessionId,
        ]);

        // Log to database
        try {
            InjectionDetection::create([
                'session_id' => $sessionId,
                'source' => $result->source->value,
                'patterns_matched' => $result->detections,
                'content_excerpt' => mb_substr($result->content, 0, 500),
                'policy_applied' => $result->policyApplied->value,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log injection detection to database', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
