<?php

use App\Models\SandboxContainer;
use App\Services\Sandbox\ContainerPool;
use App\Services\Sandbox\ProxmoxClient;

beforeEach(function () {
    $this->proxmox = Mockery::mock(ProxmoxClient::class);
    $this->pool = new ContainerPool($this->proxmox);
});

test('claim returns a ready container and marks it busy', function () {
    SandboxContainer::create([
        'vmid' => 9100,
        'node' => 'pve',
        'status' => 'ready',
        'ip_address' => '10.0.0.1',
    ]);

    $container = $this->pool->claim('job-123');

    expect($container)->not->toBeNull();
    expect($container->status)->toBe('busy');
    expect($container->claimed_by_job)->toBe('job-123');
    expect($container->claimed_at)->not->toBeNull();
});

test('claim returns null when no ready containers', function () {
    SandboxContainer::create([
        'vmid' => 9100,
        'node' => 'pve',
        'status' => 'busy',
        'claimed_by_job' => 'other-job',
        'claimed_at' => now(),
    ]);

    $container = $this->pool->claim('job-456');

    expect($container)->toBeNull();
});

test('release resets container to ready state', function () {
    $container = SandboxContainer::create([
        'vmid' => 9100,
        'node' => 'pve',
        'status' => 'busy',
        'claimed_by_job' => 'job-123',
        'claimed_at' => now(),
    ]);

    $this->pool->release($container);

    $container->refresh();
    expect($container->status)->toBe('ready');
    expect($container->claimed_by_job)->toBeNull();
    expect($container->claimed_at)->toBeNull();
});

test('cleanup releases stale containers', function () {
    SandboxContainer::create([
        'vmid' => 9100,
        'node' => 'pve',
        'status' => 'busy',
        'claimed_by_job' => 'stale-job',
        'claimed_at' => now()->subMinutes(15),
    ]);

    SandboxContainer::create([
        'vmid' => 9101,
        'node' => 'pve',
        'status' => 'busy',
        'claimed_by_job' => 'active-job',
        'claimed_at' => now()->subMinutes(2),
    ]);

    $cleaned = $this->pool->cleanup();

    expect($cleaned)->toBe(1);
    expect(SandboxContainer::where('vmid', 9100)->first()->status)->toBe('ready');
    expect(SandboxContainer::where('vmid', 9101)->first()->status)->toBe('busy');
});

test('provision creates new container from template', function () {
    config([
        'sandbox.pool.template_vmid' => 9000,
        'sandbox.pool.max_total' => 5,
        'sandbox.pool.vmid_range' => ['start' => 9100, 'end' => 9199],
        'sandbox.proxmox.node' => 'pve',
    ]);

    $this->proxmox->shouldReceive('cloneContainer')
        ->with(9000, 9100, 'pve')
        ->once();
    $this->proxmox->shouldReceive('startContainer')
        ->with(9100, 'pve')
        ->once();
    $this->proxmox->shouldReceive('getContainerStatus')
        ->andReturn(['ip' => '10.0.0.5']);

    $container = $this->pool->provision();

    expect($container)->not->toBeNull();
    expect($container->vmid)->toBe(9100);
    expect($container->status)->toBe('ready');
    expect($container->ip_address)->toBe('10.0.0.5');
});

test('provision respects max total limit', function () {
    config(['sandbox.pool.max_total' => 1]);

    SandboxContainer::create([
        'vmid' => 9100,
        'node' => 'pve',
        'status' => 'ready',
    ]);

    $container = $this->pool->provision();

    expect($container)->toBeNull();
});

test('destroy removes container from proxmox and database', function () {
    $container = SandboxContainer::create([
        'vmid' => 9100,
        'node' => 'pve',
        'status' => 'ready',
    ]);

    $this->proxmox->shouldReceive('stopContainer')->with(9100, 'pve')->once();
    $this->proxmox->shouldReceive('destroyContainer')->with(9100, 'pve')->once();

    $this->pool->destroy($container);

    expect(SandboxContainer::where('vmid', 9100)->exists())->toBeFalse();
});
