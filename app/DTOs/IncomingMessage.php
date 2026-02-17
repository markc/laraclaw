<?php

namespace App\DTOs;

class IncomingMessage
{
    public function __construct(
        public readonly string $channel,
        public readonly string $sessionKey,
        public readonly string $content,
        public readonly string $sender = 'operator',
        public readonly array $attachments = [],
        public readonly array $metadata = [],
        public readonly ?int $userId = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
        public readonly ?string $systemPrompt = null,
    ) {}
}
