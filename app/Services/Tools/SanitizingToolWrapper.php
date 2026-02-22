<?php

namespace App\Services\Tools;

use App\Enums\ContentSource;
use App\Listeners\LogToolExecution;
use App\Services\Security\ContentSanitizer;
use App\Services\Security\InjectionAuditLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SanitizingToolWrapper implements Tool
{
    public function __construct(
        protected Tool $inner,
        protected ContentSanitizer $sanitizer,
        protected InjectionAuditLog $auditLog,
    ) {}

    public function name(): string
    {
        return $this->inner->name();
    }

    public function description(): string
    {
        return $this->inner->description();
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->inner->schema($schema);
    }

    public function handle(Request $request): string
    {
        $result = $this->inner->handle($request);

        $session = LogToolExecution::getSession();
        $trustLevel = $session?->trust_level ?? 'restricted';

        $sanitizeResult = $this->sanitizer->sanitize(
            $result,
            ContentSource::ToolOutput,
            $trustLevel,
        );

        if ($sanitizeResult->injectionDetected) {
            $this->auditLog->log($sanitizeResult, $session?->id);
        }

        return $sanitizeResult->content;
    }
}
