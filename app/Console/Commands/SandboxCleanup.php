<?php

namespace App\Console\Commands;

use App\Models\SandboxContainer;
use App\Services\Sandbox\ContainerPool;
use Illuminate\Console\Command;

class SandboxCleanup extends Command
{
    protected $signature = 'sandbox:cleanup
        {--destroy-all : Destroy all sandbox containers}';

    protected $description = 'Clean up stale sandbox containers';

    public function handle(ContainerPool $pool): int
    {
        if ($this->option('destroy-all')) {
            $containers = SandboxContainer::all();
            $this->info("Destroying {$containers->count()} container(s)...");

            foreach ($containers as $container) {
                $pool->destroy($container);
                $this->info("  Destroyed VMID {$container->vmid}");
            }

            $this->info('All sandbox containers destroyed.');

            return self::SUCCESS;
        }

        $cleaned = $pool->cleanup();
        $this->info("Cleaned up {$cleaned} stale container(s).");

        return self::SUCCESS;
    }
}
