<?php

namespace App\Services\Routines;

class EventFilterEvaluator
{
    /**
     * Evaluate an event filter against a payload.
     *
     * Supports:
     * - Exact match: ['key' => 'value']
     * - Glob wildcards: ['key' => 'user.*']
     * - Existence check: ['key' => '__exists__']
     * - Nested dot notation: ['data.user.role' => 'admin']
     *
     * All filter conditions must match (AND logic).
     */
    public function evaluate(array $filter, array $payload): bool
    {
        foreach ($filter as $key => $expected) {
            $actual = data_get($payload, $key);

            if ($expected === '__exists__') {
                if ($actual === null) {
                    return false;
                }

                continue;
            }

            if (is_string($expected) && str_contains($expected, '*')) {
                if (! is_string($actual) || ! fnmatch($expected, $actual)) {
                    return false;
                }

                continue;
            }

            if ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }
}
