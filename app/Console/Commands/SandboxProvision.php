<?php

namespace App\Console\Commands;

use App\Services\Sandbox\ContainerPool;
use Illuminate\Console\Command;

class SandboxProvision extends Command
{
    protected $signature = 'sandbox:provision {count=2 : Number of containers to provision}';

    protected $description = 'Provision sandbox containers for tool execution';

    public function handle(ContainerPool $pool): int
    {
        $count = (int) $this->argument('count');

        $this->info("Provisioning {$count} sandbox container(s)...");

        $provisioned = 0;
        for ($i = 0; $i < $count; $i++) {
            $this->info('  Provisioning container '.($i + 1)."/{$count}...");
            $container = $pool->provision();

            if ($container) {
                $this->info("  Created VMID {$container->vmid} (IP: {$container->ip_address})");
                $provisioned++;
            } else {
                $this->error('  Failed to provision container '.($i + 1));
            }
        }

        $this->info("Provisioned {$provisioned}/{$count} container(s).");

        return $provisioned === $count ? self::SUCCESS : self::FAILURE;
    }
}
