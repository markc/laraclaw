<?php

namespace App\Enums;

enum TriggerType: string
{
    case Cron = 'cron';
    case Event = 'event';
    case Webhook = 'webhook';
    case Health = 'health';
}
