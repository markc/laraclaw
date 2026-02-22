<?php

namespace App\Contracts;

use App\DTOs\ClassifiedIntent;
use App\DTOs\IncomingMessage;
use App\Models\AgentSession;

interface CommandHandler
{
    public function name(): string;

    /**
     * @return array<string>
     */
    public function aliases(): array;

    public function description(): string;

    public function handle(IncomingMessage $message, array $args, ?AgentSession $session): ClassifiedIntent;
}
