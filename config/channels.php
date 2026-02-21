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
    | MCP Channel (Model Context Protocol)
    |--------------------------------------------------------------------------
    */

    'mcp' => [
        'enabled' => true,
        'trust_level' => 'operator',
        'max_message_length' => 32000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled Channel (Agent Heartbeat)
    |--------------------------------------------------------------------------
    */

    'scheduled' => [
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

        'imap' => [
            'host' => env('AGENT_EMAIL_IMAP_HOST', 'localhost'),
            'port' => (int) env('AGENT_EMAIL_IMAP_PORT', 993),
            'encryption' => env('AGENT_EMAIL_IMAP_ENCRYPTION', 'ssl'),
            'validate_cert' => (bool) env('AGENT_EMAIL_IMAP_VALIDATE_CERT', true),
            'username' => env('AGENT_EMAIL_IMAP_USERNAME'),
            'password' => env('AGENT_EMAIL_IMAP_PASSWORD'),
            'folder' => env('AGENT_EMAIL_IMAP_FOLDER', 'INBOX'),
        ],
    ],

];
