<?php

declare(strict_types=1);

namespace App\Monitoring\Enum;

use App\Monitoring\Config\HttpCheckConfig;
use App\Monitoring\Config\TcpCheckConfig;

enum CheckType: string
{
    case HTTP = 'http';
    case TCP = 'tcp';

    /** @return class-string */
    public function configClass(): string
    {
        return match ($this) {
            self::HTTP => HttpCheckConfig::class,
            self::TCP => TcpCheckConfig::class,
        };
    }
}
