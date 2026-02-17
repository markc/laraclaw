<?php

namespace App\Services\Tools\BuiltIn;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CurrentDateTimeTool implements Tool
{
    public function description(): string
    {
        return 'Get the current date, time, and timezone.';
    }

    public function handle(Request $request): string
    {
        $now = now();

        return sprintf(
            "Current date: %s\nCurrent time: %s\nTimezone: %s\nUnix timestamp: %d",
            $now->format('Y-m-d (l)'),
            $now->format('H:i:s'),
            $now->timezoneName,
            $now->timestamp,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
