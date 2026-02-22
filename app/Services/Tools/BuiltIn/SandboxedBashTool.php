<?php

namespace App\Services\Tools\BuiltIn;

use App\Services\Sandbox\SandboxExecutor;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SandboxedBashTool implements Tool
{
    public function __construct(
        protected SandboxExecutor $executor,
    ) {}

    public function name(): string
    {
        return 'bash';
    }

    public function description(): string
    {
        return 'Execute a shell command in a sandboxed container. Only available at operator trust level. Commands time out after 30 seconds.';
    }

    public function handle(Request $request): string
    {
        $command = $request['command'] ?? '';
        $workingDir = $request['working_directory'] ?? null;

        if (empty($command)) {
            return 'Error: command parameter is required.';
        }

        try {
            $output = $this->executor->execute($command, $workingDir);

            // Truncate large output
            if (mb_strlen($output) > 8000) {
                $output = mb_substr($output, 0, 8000).'... [truncated]';
            }

            return $output;
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'command' => $schema->string()
                ->description('The shell command to execute')
                ->required(),
            'working_directory' => $schema->string()
                ->description('Optional working directory for the command'),
        ];
    }
}
