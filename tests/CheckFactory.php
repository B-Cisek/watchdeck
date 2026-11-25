<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Check;
use App\Entity\Service;
use App\Enum\CheckType;
use Symfony\Component\Uid\Uuid;

final class CheckFactory
{
    /** @param array<string, mixed> $config */
    public static function create(
        array $config,
        CheckType $type = CheckType::HTTP,
        int $intervalSeconds = 60,
        int $timeoutSeconds = 5,
    ): Check {
        return new Check(
            Uuid::v7(),
            new Service(Uuid::v7(), 'shop'),
            'check',
            $type,
            $config,
            $intervalSeconds,
            $timeoutSeconds,
        );
    }
}
