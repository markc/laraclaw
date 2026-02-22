<?php

namespace App\Enums;

enum SanitizePolicy: string
{
    case Block = 'block';
    case Warn = 'warn';
    case Sanitize = 'sanitize';
    case Allow = 'allow';
}
