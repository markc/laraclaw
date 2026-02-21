<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trust Level Policies
    |--------------------------------------------------------------------------
    | Define which tools are available at each trust level.
    | Tool names must match the name() method on each Tool class.
    */

    'policies' => [
        'operator' => [
            'allowed' => ['*'],
            'denied' => [],
            'sandbox' => 'none',
        ],
        'standard' => [
            'allowed' => ['current_datetime', 'http_request'],
            'denied' => ['bash'],
            'sandbox' => 'restricted',
        ],
        'restricted' => [
            'allowed' => ['current_datetime'],
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
        'timeout' => env('TOOL_SANDBOX_TIMEOUT', 30),
        'memory_limit' => env('TOOL_SANDBOX_MEMORY', '256m'),
    ],

];
