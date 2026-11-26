<?php

declare(strict_types=1);

namespace App\Logs\Enum;

enum LogStream: string
{
    case STDOUT = 'stdout';
    case STDERR = 'stderr';
}
