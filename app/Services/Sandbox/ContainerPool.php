<?php

namespace App\Services\Sandbox;

use App\Models\SandboxContainer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContainerPool
{
    public function __construct(
        protected ProxmoxClient $proxmox,
    ) {}

    /**
     * Claim an available container atomically.
     */
    public function claim(string $jobId): ?SandboxContainer
    {
        return DB::transaction(function () use ($jobId) {
            $container = SandboxContainer::query()
                ->ready()
                ->lockForUpdate()
                ->first();

            if (! $container) {
                Log::warning('ContainerPool: no ready containers available');

                return null;
            }

            $container->update([
                'status' => 'busy',
                'claimed_by_job' => $jobId,
                'claimed_at' => now(),
            ]);

            return $container;
        });
    }

    /**
     * Release a container back to the ready pool.
     */
    public function release(SandboxContainer $container): void
    {
        $container->update([
            'status' => 'ready',
            'claimed_by_job' => null,
            'claimed_at' => null,
        ]);
    }

    /**
     * Provision a new container by cloning the template.
     */
    public function provision(): ?SandboxContainer
    {
        $pool = config('sandbox.pool');

        // Check max total limit
        $totalCount = SandboxContainer::count();
        if ($totalCount >= $pool['max_total']) {
            Log::warning('ContainerPool: max total containers reached', ['total' => $totalCount]);

            return null;
        }

        // Find next available VMID
        $vmid = $this->nextAvailableVmid();
        if (! $vmid) {
            Log::warning('ContainerPool: no VMIDs available in range');

            return null;
        }

        $node = config('sandbox.proxmox.node');

        // Create record first
        $container = SandboxContainer::create([
            'vmid' => $vmid,
            'node' => $node,
            'status' => 'provisioning',
        ]);

        try {
            $this->proxmox->cloneContainer($pool['template_vmid'], $vmid, $node);
            $this->proxmox->startContainer($vmid, $node);

            // Wait for container to get an IP
            $ip = $this->waitForIp($vmid, $node);

            $container->update([
                'status' => 'ready',
                'ip_address' => $ip,
            ]);

            return $container;
        } catch (\Throwable $e) {
            Log::error('ContainerPool: provisioning failed', [
                'vmid' => $vmid,
                'error' => $e->getMessage(),
            ]);

            $container->update(['status' => 'destroying']);

            try {
                $this->proxmox->destroyContainer($vmid, $node);
            } catch (\Throwable) {
                // Best effort cleanup
            }

            $container->delete();

            return null;
        }
    }

    /**
     * Destroy a container and remove from pool.
     */
    public function destroy(SandboxContainer $container): void
    {
        $container->update(['status' => 'destroying']);

        try {
            $this->proxmox->stopContainer($container->vmid, $container->node);
        } catch (\Throwable) {
            // May already be stopped
        }

        try {
            $this->proxmox->destroyContainer($container->vmid, $container->node);
        } catch (\Throwable $e) {
            Log::error('ContainerPool: destroy failed', [
                'vmid' => $container->vmid,
                'error' => $e->getMessage(),
            ]);
        }

        $container->delete();
    }

    /**
     * Clean up stale containers (busy but claimed >10min ago).
     */
    public function cleanup(): int
    {
        $stale = SandboxContainer::query()
            ->busy()
            ->where('claimed_at', '<', now()->subMinutes(10))
            ->get();

        $cleaned = 0;
        foreach ($stale as $container) {
            $this->release($container);
            $cleaned++;
        }

        return $cleaned;
    }

    protected function nextAvailableVmid(): ?int
    {
        $range = config('sandbox.pool.vmid_range');
        $existingVmids = SandboxContainer::pluck('vmid')->all();

        for ($vmid = $range['start']; $vmid <= $range['end']; $vmid++) {
            if (! in_array($vmid, $existingVmids)) {
                return $vmid;
            }
        }

        return null;
    }

    protected function waitForIp(int $vmid, string $node, int $timeoutSeconds = 30): ?string
    {
        $start = time();
        while (time() - $start < $timeoutSeconds) {
            $status = $this->proxmox->getContainerStatus($vmid, $node);
            $ip = $status['ip'] ?? null;

            if ($ip) {
                return $ip;
            }

            sleep(2);
        }

        return null;
    }
}
