<?php

declare(strict_types=1);

namespace App\Logs\Entity;

use App\Logs\Enum\LogStream;
use App\Logs\Repository\LogLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LogLineRepository::class)]
#[ORM\Table(name: '`log_lines`')]
#[ORM\Index(fields: ['logSource', 'loggedAt'])]
class LogLine
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly LogSource $logSource,
        /** Changes when the container is recreated; the source stays. */
        #[ORM\Column(length: 64)]
        private readonly string $dockerId,
        // DBAL declares datetime as TIMESTAMP(0) on PostgreSQL, which would drop the fraction of a second.
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, columnDefinition: 'TIMESTAMP(6) WITHOUT TIME ZONE NOT NULL')]
        private readonly \DateTimeImmutable $loggedAt,
        #[ORM\Column(length: 6, enumType: LogStream::class)]
        private readonly LogStream $stream,
        #[ORM\Column(type: Types::TEXT)]
        private readonly string $message,
        /** Vector delivers at least once, so the same line may come twice. */
        #[ORM\Column(length: 64, unique: true)]
        private readonly string $eventHash,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogSource(): LogSource
    {
        return $this->logSource;
    }

    public function getDockerId(): string
    {
        return $this->dockerId;
    }

    public function getLoggedAt(): \DateTimeImmutable
    {
        return $this->loggedAt;
    }

    public function getStream(): LogStream
    {
        return $this->stream;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
