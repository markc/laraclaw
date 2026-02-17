<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Chat Channel
    |--------------------------------------------------------------------------
    */

    'web' => [
        'enabled' => true,
        'trust_level' => 'operator',
        'max_message_length' => 32000,
    ],

    /*
    |--------------------------------------------------------------------------
    | TUI Channel (artisan agent:chat)
    |--------------------------------------------------------------------------
    */

    'tui' => [
        'enabled' => true,
        'trust_level' => 'operator',
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Channel
    |--------------------------------------------------------------------------
    */

    'email' => [
        'enabled' => env('AGENT_EMAIL_ENABLED', false),
        'trust_level' => 'standard',
        'address' => env('AGENT_EMAIL_ADDRESS', 'agent@localhost'),
        'allow_from' => array_filter(explode(',', env('AGENT_EMAIL_ALLOW_FROM', ''))),
        'dm_policy' => env('AGENT_EMAIL_DM_POLICY', 'allowlist'), // allowlist, pairing, open
        'max_attachment_size' => 10 * 1024 * 1024, // 10MB
        'allowed_attachment_types' => ['text/plain', 'application/pdf', 'image/*', 'text/csv'],
    ],

];
