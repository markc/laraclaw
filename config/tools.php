<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trust Level Policies
    |--------------------------------------------------------------------------
    | Define which tools are available at each trust level.
    */

    'policies' => [
        'operator' => [
            'allowed' => ['*'],
            'denied' => [],
            'sandbox' => 'none',
        ],
        'standard' => [
            'allowed' => ['current_datetime', 'http_request', 'email_send'],
            'denied' => ['bash', 'file_operation', 'database_query'],
            'sandbox' => 'restricted',
        ],
        'restricted' => [
            'allowed' => ['current_datetime', 'http_request'],
            'denied' => ['*'],
            'sandbox' => 'docker',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox Settings
    |--------------------------------------------------------------------------
    */

    'sandbox' => [
        'docker_image' => env('TOOL_SANDBOX_IMAGE', 'php:8.4-cli-alpine'),
        'timeout' => env('TOOL_SANDBOX_TIMEOUT', 30),
        'memory_limit' => env('TOOL_SANDBOX_MEMORY', '256m'),
    ],

];
