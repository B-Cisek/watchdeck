<?php

declare(strict_types=1);

namespace App\Logs\Dto;

use App\Logs\Entity\LogSource;
use Symfony\Component\Uid\Uuid;

final readonly class LogSourceResponse
{
    public function __construct(
        public Uuid $id,
        public Uuid $serviceId,
        public string $containerName,
        public ?string $host,
        public ?\DateTimeImmutable $lastLogAt,
    ) {}

    public static function fromEntity(LogSource $source): self
    {
        return new self(
            $source->getId(),
            $source->getService()->getId(),
            $source->getContainerName(),
            $source->getHost(),
            $source->getLastLogAt(),
        );
    }
}
