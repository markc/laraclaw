<?php

namespace App\Services\Tools\BuiltIn;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Symfony\Component\Process\Process;

class BashTool implements Tool
{
    public function name(): string
    {
        return 'bash';
    }

    public function description(): string
    {
        return 'Execute a shell command on the server. Only available at operator trust level. Returns stdout and stderr. Commands time out after 30 seconds.';
    }

    public function handle(Request $request): string
    {
        $command = $request['command'] ?? '';
        $workingDir = $request['working_directory'] ?? null;

        if (empty($command)) {
            return 'Error: command parameter is required.';
        }

        // Block destructive patterns
        if ($this->isDangerous($command)) {
            return 'Error: this command pattern is blocked for safety.';
        }

        try {
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(30);

            if ($workingDir) {
                $process->setWorkingDirectory($workingDir);
            }

            $process->run();

            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();
            $exitCode = $process->getExitCode();

            $result = "Exit code: {$exitCode}";

            if (! empty($output)) {
                // Truncate large output
                if (mb_strlen($output) > 8000) {
                    $output = mb_substr($output, 0, 8000).'... [truncated]';
                }
                $result .= "\n\nStdout:\n{$output}";
            }

            if (! empty($errorOutput)) {
                if (mb_strlen($errorOutput) > 2000) {
                    $errorOutput = mb_substr($errorOutput, 0, 2000).'... [truncated]';
                }
                $result .= "\n\nStderr:\n{$errorOutput}";
            }

            return $result;
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            $schema->string('command')
                ->description('The shell command to execute')
                ->required(),
            $schema->string('working_directory')
                ->description('Optional working directory for the command'),
        ];
    }

    protected function isDangerous(string $command): bool
    {
        $blocked = [
            'rm -rf /',
            'mkfs',
            'dd if=',
            ':(){:|:&};:',  // fork bomb
            'chmod -R 777 /',
            '> /dev/sda',
        ];

        $lower = strtolower($command);

        foreach ($blocked as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
