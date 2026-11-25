<?php

declare(strict_types=1);

namespace App\Enum;

use App\Config\HttpCheckConfig;
use App\Config\TcpCheckConfig;

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
