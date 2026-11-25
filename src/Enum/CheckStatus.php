<?php

declare(strict_types=1);

namespace App\Enum;

enum CheckStatus: string
{
    case PENDING = 'pending';
    case UP = 'up';
    case DOWN = 'down';
}
