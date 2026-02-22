<?php

namespace App\Services\Sandbox;

use Illuminate\Support\Str;

class SandboxExecutor
{
    public function __construct(
        protected ContainerPool $pool,
        protected ProxmoxClient $proxmox,
    ) {}

    /**
     * Execute a command in a sandboxed container.
     */
    public function execute(string $command, ?string $workingDir = null): string
    {
        $jobId = Str::uuid()->toString();
        $container = $this->pool->claim($jobId);

        if (! $container) {
            return 'Error: no sandbox containers available. Please try again later.';
        }

        try {
            $fullCommand = $command;
            if ($workingDir) {
                $fullCommand = 'cd '.escapeshellarg($workingDir)." && {$command}";
            }

            return $this->proxmox->exec($container->vmid, $fullCommand, $container->node);
        } finally {
            $this->pool->release($container);
        }
    }
}
