<?php

namespace App\Enums;

enum IntentType: string
{
    case Command = 'command';
    case Query = 'query';
    case Task = 'task';
}
