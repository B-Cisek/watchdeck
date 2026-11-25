<?php

declare(strict_types=1);

namespace App\Monitoring\Dto;

use App\Monitoring\Entity\Check;
use App\Monitoring\Enum\CheckStatus;
use App\Monitoring\Enum\CheckType;
use Symfony\Component\Uid\Uuid;

final readonly class CheckResponse
{
    /** @param array<string, mixed> $config */
    public function __construct(
        public Uuid $id,
        public Uuid $serviceId,
        public string $name,
        public CheckType $type,
        public array $config,
        public int $intervalSeconds,
        public int $timeoutSeconds,
        public CheckStatus $status,
        public ?\DateTimeImmutable $lastCheckedAt,
    ) {
    }

    public static function fromEntity(Check $check): self
    {
        return new self(
            $check->getId(),
            $check->getService()->getId(),
            $check->getName(),
            $check->getType(),
            $check->getConfig(),
            $check->getIntervalSeconds(),
            $check->getTimeoutSeconds(),
            $check->getStatus(),
            $check->getLastCheckedAt(),
        );
    }
}
