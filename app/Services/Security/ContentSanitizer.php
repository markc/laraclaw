<?php

namespace App\Services\Security;

use App\DTOs\SanitizeResult;
use App\Enums\ContentSource;
use App\Enums\SanitizePolicy;

class ContentSanitizer
{
    /**
     * Sanitize content based on source and trust level.
     */
    public function sanitize(string $content, ContentSource $source, string $trustLevel = 'standard'): SanitizeResult
    {
        if (! config('security.sanitizer.enabled', true)) {
            return new SanitizeResult(
                content: $this->wrap($content, $source),
                injectionDetected: false,
                source: $source,
            );
        }

        $detections = $this->scan($content);

        // Check for base64-encoded payloads
        $base64Detections = $this->scanBase64($content);
        $detections = array_merge($detections, $base64Detections);

        $policy = $this->resolvePolicy($source, $trustLevel);

        if (empty($detections)) {
            return new SanitizeResult(
                content: $this->wrap($content, $source),
                injectionDetected: false,
                policyApplied: $policy,
                source: $source,
            );
        }

        $sanitized = match ($policy) {
            SanitizePolicy::Block => '[Content blocked: potential prompt injection detected]',
            SanitizePolicy::Warn => "[WARNING: Potential prompt injection detected in {$source->value}]\n".$this->wrap($content, $source),
            SanitizePolicy::Sanitize => $this->wrap($this->redactMatches($content, $detections), $source),
            SanitizePolicy::Allow => $this->wrap($content, $source),
        };

        return new SanitizeResult(
            content: $sanitized,
            injectionDetected: true,
            detections: $detections,
            policyApplied: $policy,
            source: $source,
        );
    }

    /**
     * Wrap content with source delimiter markers.
     */
    public function wrap(string $content, ContentSource $source): string
    {
        $tag = match ($source) {
            ContentSource::ToolOutput => 'TOOL_OUTPUT',
            ContentSource::EmailBody => 'EMAIL_BODY',
            ContentSource::WebhookPayload => 'WEBHOOK_PAYLOAD',
            ContentSource::UserMessage => 'USER_MESSAGE',
        };

        return "<<<{$tag}>>>\n{$content}\n<<<END_{$tag}>>>";
    }

    /**
     * Scan content for prompt injection patterns.
     *
     * @return array<string> List of matched pattern names
     */
    public function scan(string $content): array
    {
        $patterns = [
            'ignore_instructions' => '/ignore\s+(all\s+)?(previous|prior|above|earlier)\s+(instructions?|prompts?|directives?|rules?)/i',
            'new_instructions' => '/new\s+instructions?:?\s/i',
            'role_reassignment' => '/(you\s+are\s+now|from\s+now\s+on\s+you\s+are|pretend\s+you\s+are|act\s+as\s+if\s+you\s+are)\s/i',
            'role_impersonation' => '/^(system|assistant)\s*:/im',
            'special_tokens' => '/\[INST\]|\[\/INST\]|<<SYS>>|<\|im_start\|>|<\|im_end\|>|<\|endoftext\|>/i',
            'html_script_injection' => '/<script[\s>]|javascript\s*:/i',
            'act_as' => '/\bact\s+as\b.*\b(admin|root|superuser|system|developer)\b/i',
            'instruction_override' => '/(disregard|forget|override)\s+(all\s+)?(previous|prior|your|above)\s+(instructions?|rules?|guidelines?)/i',
        ];

        $detections = [];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $content)) {
                $detections[] = $name;
            }
        }

        return $detections;
    }

    /**
     * Scan for base64-encoded injection payloads.
     *
     * @return array<string>
     */
    protected function scanBase64(string $content): array
    {
        $detections = [];

        // Find potential base64 strings (min 20 chars to avoid false positives)
        if (preg_match_all('/[A-Za-z0-9+\/]{20,}={0,2}/', $content, $matches)) {
            foreach ($matches[0] as $candidate) {
                $decoded = base64_decode($candidate, true);
                if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
                    $innerDetections = $this->scan($decoded);
                    if (! empty($innerDetections)) {
                        $detections[] = 'base64_encoded:'.implode(',', $innerDetections);
                    }
                }
            }
        }

        return $detections;
    }

    /**
     * Redact matched injection patterns from content.
     */
    protected function redactMatches(string $content, array $detections): string
    {
        $patterns = [
            'ignore_instructions' => '/ignore\s+(all\s+)?(previous|prior|above|earlier)\s+(instructions?|prompts?|directives?|rules?)/i',
            'new_instructions' => '/new\s+instructions?:?\s.*$/im',
            'role_reassignment' => '/(you\s+are\s+now|from\s+now\s+on\s+you\s+are|pretend\s+you\s+are|act\s+as\s+if\s+you\s+are)\s.*$/im',
            'role_impersonation' => '/^(system|assistant)\s*:.*$/im',
            'special_tokens' => '/\[INST\]|\[\/INST\]|<<SYS>>|<\|im_start\|>|<\|im_end\|>|<\|endoftext\|>/i',
            'html_script_injection' => '/<script[^>]*>.*?<\/script>|javascript\s*:[^\s]*/is',
            'act_as' => '/\bact\s+as\b.*\b(admin|root|superuser|system|developer)\b.*$/im',
            'instruction_override' => '/(disregard|forget|override)\s+(all\s+)?(previous|prior|your|above)\s+(instructions?|rules?|guidelines?).*$/im',
        ];

        foreach ($detections as $detection) {
            // Skip base64 detections for direct redaction
            if (str_starts_with($detection, 'base64_encoded:')) {
                continue;
            }

            if (isset($patterns[$detection])) {
                $content = preg_replace($patterns[$detection], '[REDACTED]', $content);
            }
        }

        return $content;
    }

    /**
     * Resolve the sanitization policy for a source and trust level.
     */
    protected function resolvePolicy(ContentSource $source, string $trustLevel): SanitizePolicy
    {
        $policies = config('security.sanitizer.policies', []);
        $policyValue = $policies[$source->value][$trustLevel]
            ?? $policies[$source->value]['default']
            ?? 'warn';

        return SanitizePolicy::from($policyValue);
    }
}
