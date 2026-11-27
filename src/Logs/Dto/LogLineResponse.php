<?php

declare(strict_types=1);

namespace App\Logs\Dto;

use App\Logs\Entity\LogLine;
use App\Logs\Enum\LogStream;
use Symfony\Component\Uid\Uuid;

final readonly class LogLineResponse
{
    public function __construct(
        public int $id,
        /** Tells apart containers of the same name on different hosts. */
        public Uuid $sourceId,
        public string $container,
        public \DateTimeImmutable $loggedAt,
        public LogStream $stream,
        public string $message,
    ) {}

    public static function fromEntity(LogLine $line): self
    {
        return new self(
            (int) $line->getId(),
            $line->getLogSource()->getId(),
            $line->getLogSource()->getContainerName(),
            $line->getLoggedAt(),
            $line->getStream(),
            $line->getMessage(),
        );
    }
}
