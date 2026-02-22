<?php

namespace App\Services\Sandbox;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxmoxClient
{
    protected ?string $authTicket = null;

    protected ?string $csrfToken = null;

    /**
     * Authenticate and obtain a ticket (for password auth).
     */
    public function authenticate(): void
    {
        $config = config('sandbox.proxmox');

        $response = Http::withOptions(['verify' => $config['verify_cert']])
            ->post($this->baseUrl().'/api2/json/access/ticket', [
                'username' => $config['username'],
                'password' => $config['password'],
            ]);

        $response->throw();

        $data = $response->json('data');
        $this->authTicket = $data['ticket'];
        $this->csrfToken = $data['CSRFPreventionToken'];
    }

    /**
     * List containers on a node.
     */
    public function listContainers(?string $node = null): array
    {
        $node = $node ?? config('sandbox.proxmox.node');

        return $this->get("/nodes/{$node}/lxc")['data'] ?? [];
    }

    /**
     * Clone a container template.
     */
    public function cloneContainer(int $templateVmid, int $newVmid, ?string $node = null): array
    {
        $node = $node ?? config('sandbox.proxmox.node');
        $pool = config('sandbox.pool');

        return $this->post("/nodes/{$node}/lxc/{$templateVmid}/clone", [
            'newid' => $newVmid,
            'full' => 1,
            'hostname' => "sandbox-{$newVmid}",
            'memory' => $pool['memory_mb'],
        ]);
    }

    /**
     * Start a container.
     */
    public function startContainer(int $vmid, ?string $node = null): array
    {
        $node = $node ?? config('sandbox.proxmox.node');

        return $this->post("/nodes/{$node}/lxc/{$vmid}/status/start");
    }

    /**
     * Stop a container.
     */
    public function stopContainer(int $vmid, ?string $node = null): array
    {
        $node = $node ?? config('sandbox.proxmox.node');

        return $this->post("/nodes/{$node}/lxc/{$vmid}/status/stop");
    }

    /**
     * Destroy a container.
     */
    public function destroyContainer(int $vmid, ?string $node = null): array
    {
        $node = $node ?? config('sandbox.proxmox.node');

        return $this->delete("/nodes/{$node}/lxc/{$vmid}", ['purge' => 1, 'force' => 1]);
    }

    /**
     * Execute a command inside a container via the Proxmox API.
     */
    public function exec(int $vmid, string $command, ?string $node = null): string
    {
        $node = $node ?? config('sandbox.proxmox.node');
        $timeout = config('sandbox.pool.timeout', 30);

        // Use /lxc/{vmid}/exec endpoint (Proxmox 8+)
        $result = $this->post("/nodes/{$node}/lxc/{$vmid}/exec", [
            'command' => ['bash', '-c', $command],
        ]);

        // Read output — Proxmox returns a PID, we poll for completion
        $pid = $result['data'] ?? null;
        if (! $pid) {
            return 'Error: Failed to start command in container';
        }

        // Poll for completion
        $start = time();
        while (time() - $start < $timeout) {
            usleep(500000); // 0.5s

            try {
                $status = $this->get("/nodes/{$node}/lxc/{$vmid}/exec/{$pid}/status");
                if (($status['data']['exited'] ?? false)) {
                    $output = $this->get("/nodes/{$node}/lxc/{$vmid}/exec/{$pid}/log");

                    return $output['data'] ?? '';
                }
            } catch (\Throwable $e) {
                Log::warning('ProxmoxClient: exec poll error', ['error' => $e->getMessage()]);
            }
        }

        return 'Error: Command timed out after '.$timeout.'s';
    }

    /**
     * Get container status.
     */
    public function getContainerStatus(int $vmid, ?string $node = null): array
    {
        $node = $node ?? config('sandbox.proxmox.node');

        return $this->get("/nodes/{$node}/lxc/{$vmid}/status/current")['data'] ?? [];
    }

    protected function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    protected function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, $data);
    }

    protected function delete(string $path, array $data = []): array
    {
        return $this->request('DELETE', $path, $data);
    }

    protected function request(string $method, string $path, array $data = []): array
    {
        $config = config('sandbox.proxmox');
        $url = $this->baseUrl().'/api2/json'.$path;

        $http = Http::withOptions(['verify' => $config['verify_cert']])
            ->timeout(30);

        // Prefer token auth
        if ($config['token_id'] && $config['token_secret']) {
            $http = $http->withHeaders([
                'Authorization' => "PVEAPIToken={$config['token_id']}={$config['token_secret']}",
            ]);
        } elseif ($this->authTicket) {
            $http = $http->withHeaders([
                'Cookie' => "PVEAuthCookie={$this->authTicket}",
                'CSRFPreventionToken' => $this->csrfToken,
            ]);
        } else {
            $this->authenticate();

            return $this->request($method, $path, $data);
        }

        $response = match ($method) {
            'GET' => $http->get($url, $data),
            'POST' => $http->post($url, $data),
            'DELETE' => $http->delete($url, $data),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        $response->throw();

        return $response->json() ?? [];
    }

    protected function baseUrl(): string
    {
        return rtrim(config('sandbox.proxmox.host'), '/');
    }
}
