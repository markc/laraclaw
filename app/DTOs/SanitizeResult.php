<?php

namespace App\DTOs;

use App\Enums\ContentSource;
use App\Enums\SanitizePolicy;

class SanitizeResult
{
    public function __construct(
        public readonly string $content,
        public readonly bool $injectionDetected,
        public readonly array $detections = [],
        public readonly SanitizePolicy $policyApplied = SanitizePolicy::Allow,
        public readonly ContentSource $source = ContentSource::UserMessage,
    ) {}
}
