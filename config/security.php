<?php

use App\Enums\ContentSource;

return [

    /*
    |--------------------------------------------------------------------------
    | Content Sanitizer
    |--------------------------------------------------------------------------
    */

    'sanitizer' => [
        'enabled' => (bool) env('SECURITY_SANITIZER_ENABLED', true),
        'log_detections' => (bool) env('SECURITY_LOG_DETECTIONS', true),

        /*
        |----------------------------------------------------------------------
        | Policies — source × trust_level → action
        |----------------------------------------------------------------------
        | Values: block, warn, sanitize, allow
        */

        'policies' => [
            ContentSource::ToolOutput->value => [
                'operator' => 'warn',
                'standard' => 'sanitize',
                'restricted' => 'block',
                'default' => 'warn',
            ],
            ContentSource::EmailBody->value => [
                'operator' => 'warn',
                'standard' => 'sanitize',
                'restricted' => 'block',
                'default' => 'sanitize',
            ],
            ContentSource::WebhookPayload->value => [
                'operator' => 'warn',
                'standard' => 'sanitize',
                'restricted' => 'block',
                'default' => 'sanitize',
            ],
            ContentSource::UserMessage->value => [
                'operator' => 'allow',
                'standard' => 'warn',
                'restricted' => 'warn',
                'default' => 'allow',
            ],
        ],
    ],

];
