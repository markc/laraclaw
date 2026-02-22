<?php

namespace App\DTOs;

use App\Enums\IntentType;

class ClassifiedIntent
{
    public function __construct(
        public readonly IntentType $type,
        public readonly string $message,
        public readonly ?string $commandName = null,
        public readonly array $commandArgs = [],
        public readonly ?string $response = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Whether this intent should short-circuit the LLM call.
     */
    public function isShortCircuit(): bool
    {
        return $this->response !== null;
    }
}
