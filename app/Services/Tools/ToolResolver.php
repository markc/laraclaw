<?php

namespace App\Services\Tools;

use App\Models\AgentSession;
use App\Services\Tools\BuiltIn\BashTool;
use App\Services\Tools\BuiltIn\CurrentDateTimeTool;
use App\Services\Tools\BuiltIn\HttpRequestTool;
use Laravel\Ai\Contracts\Tool;

class ToolResolver
{
    /**
     * All registered built-in tools, keyed by policy name.
     *
     * @return array<string, class-string<Tool>>
     */
    protected function registry(): array
    {
        return [
            'current_datetime' => CurrentDateTimeTool::class,
            'http_request' => HttpRequestTool::class,
            'bash' => BashTool::class,
        ];
    }

    /**
     * Resolve tools available for the given session's trust level.
     *
     * @return list<Tool>
     */
    public function resolve(AgentSession $session): array
    {
        $trustLevel = $session->trust_level
            ?? config('channels.'.$session->channel.'.trust_level', 'restricted');

        $policy = config("tools.policies.{$trustLevel}", config('tools.policies.restricted'));

        $allowed = $policy['allowed'] ?? [];
        $denied = $policy['denied'] ?? [];

        return collect($this->registry())
            ->filter(fn ($class, $name) => $this->isAllowed($name, $allowed, $denied))
            ->map(fn ($class) => app($class))
            ->values()
            ->all();
    }

    protected function isAllowed(string $toolName, array $allowed, array $denied): bool
    {
        // Explicit deny takes precedence
        if (in_array('*', $denied) || in_array($toolName, $denied)) {
            // But explicit allow overrides wildcard deny
            if (in_array('*', $denied) && in_array($toolName, $allowed)) {
                return true;
            }

            return false;
        }

        // Wildcard allow or explicit allow
        return in_array('*', $allowed) || in_array($toolName, $allowed);
    }
}
