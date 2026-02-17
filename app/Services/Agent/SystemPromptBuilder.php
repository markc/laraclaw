<?php

namespace App\Services\Agent;

use App\Models\AgentSession;
use Illuminate\Support\Facades\Storage;

class SystemPromptBuilder
{
    /**
     * Compose the system prompt from workspace files + session overrides.
     */
    public function build(AgentSession $session): string
    {
        $parts = [];

        // Session-level system prompt override takes precedence
        if ($session->system_prompt) {
            return $session->system_prompt;
        }

        // Agent-level prompt overrides
        $overrides = $session->agent->prompt_overrides ?? [];

        // Load AGENTS.md (core instructions)
        $parts[] = $this->loadPromptFile($session, 'AGENTS.md', $overrides);

        // Load SOUL.md (personality)
        $soul = $this->loadPromptFile($session, 'SOUL.md', $overrides);
        if ($soul) {
            $parts[] = $soul;
        }

        // Load TOOLS.md (tool conventions)
        $tools = $this->loadPromptFile($session, 'TOOLS.md', $overrides);
        if ($tools) {
            $parts[] = $tools;
        }

        // Load MEMORY.md (curated long-term facts)
        $memory = $this->loadPromptFile($session, 'MEMORY.md', $overrides);
        if ($memory) {
            $parts[] = $memory;
        }

        $prompt = implode("\n\n---\n\n", array_filter($parts));

        return $prompt ?: 'You are a helpful AI assistant.';
    }

    protected function loadPromptFile(AgentSession $session, string $filename, array $overrides): ?string
    {
        // Check for override in agent config
        if (isset($overrides[$filename])) {
            return $overrides[$filename];
        }

        // Check workspace path
        $workspacePath = $session->agent->workspace_path ?? config('agent.workspace_path');
        $filePath = $workspacePath . '/' . $filename;

        if (file_exists($filePath)) {
            return file_get_contents($filePath);
        }

        // Check storage disk
        $storagePath = 'agent/' . $filename;
        if (Storage::exists($storagePath)) {
            return Storage::get($storagePath);
        }

        return null;
    }
}
