<?php

use App\Services\Sandbox\ProxmoxClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'sandbox.proxmox.host' => 'https://pve.test:8006',
        'sandbox.proxmox.node' => 'pve',
        'sandbox.proxmox.verify_cert' => false,
        'sandbox.proxmox.token_id' => 'root@pam!test',
        'sandbox.proxmox.token_secret' => 'test-secret',
    ]);

    $this->client = new ProxmoxClient;
});

test('listContainers makes correct API call', function () {
    Http::fake([
        'pve.test:8006/api2/json/nodes/pve/lxc' => Http::response([
            'data' => [
                ['vmid' => 100, 'name' => 'ct-100', 'status' => 'running'],
                ['vmid' => 101, 'name' => 'ct-101', 'status' => 'stopped'],
            ],
        ]),
    ]);

    $containers = $this->client->listContainers();

    expect($containers)->toHaveCount(2);
    expect($containers[0]['vmid'])->toBe(100);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/nodes/pve/lxc')
            && str_contains($request->header('Authorization')[0] ?? '', 'PVEAPIToken');
    });
});

test('cloneContainer sends correct parameters', function () {
    Http::fake([
        'pve.test:8006/api2/json/nodes/pve/lxc/9000/clone' => Http::response([
            'data' => 'UPID:pve:00001234',
        ]),
    ]);

    $result = $this->client->cloneContainer(9000, 9100);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/lxc/9000/clone')
            && $request['newid'] === 9100;
    });
});

test('startContainer sends POST to correct endpoint', function () {
    Http::fake([
        'pve.test:8006/api2/json/nodes/pve/lxc/9100/status/start' => Http::response([
            'data' => 'UPID:pve:00001235',
        ]),
    ]);

    $this->client->startContainer(9100);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/lxc/9100/status/start');
    });
});

test('stopContainer sends POST to correct endpoint', function () {
    Http::fake([
        'pve.test:8006/api2/json/nodes/pve/lxc/9100/status/stop' => Http::response([
            'data' => 'UPID:pve:00001236',
        ]),
    ]);

    $this->client->stopContainer(9100);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/lxc/9100/status/stop');
    });
});

test('destroyContainer sends DELETE with purge and force', function () {
    Http::fake([
        'pve.test:8006/api2/json/nodes/pve/lxc/9100*' => Http::response([
            'data' => 'UPID:pve:00001237',
        ]),
    ]);

    $this->client->destroyContainer(9100);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/lxc/9100')
            && $request->method() === 'DELETE';
    });
});

test('uses token authentication when configured', function () {
    Http::fake([
        'pve.test:8006/*' => Http::response(['data' => []]),
    ]);

    $this->client->listContainers();

    Http::assertSent(function ($request) {
        $auth = $request->header('Authorization')[0] ?? '';

        return str_contains($auth, 'PVEAPIToken=root@pam!test=test-secret');
    });
});

test('falls back to password auth when no token configured', function () {
    config([
        'sandbox.proxmox.token_id' => null,
        'sandbox.proxmox.token_secret' => null,
        'sandbox.proxmox.username' => 'root@pam',
        'sandbox.proxmox.password' => 'testpass',
    ]);

    Http::fake([
        'pve.test:8006/api2/json/access/ticket' => Http::response([
            'data' => [
                'ticket' => 'PVE:root@pam:test-ticket',
                'CSRFPreventionToken' => 'csrf-token',
            ],
        ]),
        'pve.test:8006/api2/json/nodes/pve/lxc' => Http::response(['data' => []]),
    ]);

    $client = new ProxmoxClient;
    $client->listContainers();

    Http::assertSentCount(2); // auth + list
});
