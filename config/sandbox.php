<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sandbox Driver
    |--------------------------------------------------------------------------
    | 'none' — tools run locally (default)
    | 'proxmox' — tools run inside Proxmox CT containers
    */

    'driver' => env('SANDBOX_DRIVER', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Proxmox VE Connection
    |--------------------------------------------------------------------------
    */

    'proxmox' => [
        'host' => env('PROXMOX_HOST', 'https://192.168.2.1:8006'),
        'node' => env('PROXMOX_NODE', 'pve'),
        'verify_cert' => (bool) env('PROXMOX_VERIFY_CERT', false),

        // Token-based auth (preferred)
        'token_id' => env('PROXMOX_TOKEN_ID'),
        'token_secret' => env('PROXMOX_TOKEN_SECRET'),

        // Password auth (fallback)
        'username' => env('PROXMOX_USERNAME', 'root@pam'),
        'password' => env('PROXMOX_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Container Pool
    |--------------------------------------------------------------------------
    */

    'pool' => [
        'template_vmid' => (int) env('SANDBOX_TEMPLATE_VMID', 9000),
        'min_ready' => (int) env('SANDBOX_MIN_READY', 2),
        'max_total' => (int) env('SANDBOX_MAX_TOTAL', 5),
        'vmid_range' => [
            'start' => (int) env('SANDBOX_VMID_START', 9100),
            'end' => (int) env('SANDBOX_VMID_END', 9199),
        ],
        'memory_mb' => (int) env('SANDBOX_MEMORY_MB', 256),
        'disk_gb' => (int) env('SANDBOX_DISK_GB', 1),
        'timeout' => (int) env('SANDBOX_TIMEOUT', 30),
    ],

];
