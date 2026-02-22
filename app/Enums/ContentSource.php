<?php

namespace App\Enums;

enum ContentSource: string
{
    case ToolOutput = 'tool_output';
    case EmailBody = 'email_body';
    case WebhookPayload = 'webhook_payload';
    case UserMessage = 'user_message';
}
