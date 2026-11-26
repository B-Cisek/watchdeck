<?php

declare(strict_types=1);

namespace App\Logs\Dto;

use App\Logs\Entity\LogLine;
use App\Logs\Enum\LogStream;

final readonly class LogLineResponse
{
    public function __construct(
        public int $id,
        public string $container,
        public \DateTimeImmutable $loggedAt,
        public LogStream $stream,
        public string $message,
    ) {}

    public static function fromEntity(LogLine $line): self
    {
        return new self(
            (int) $line->getId(),
            $line->getLogSource()->getContainerName(),
            $line->getLoggedAt(),
            $line->getStream(),
            $line->getMessage(),
        );
    }
}
