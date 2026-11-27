<?php

declare(strict_types=1);

namespace App\Logs\Entity;

use App\Catalog\Entity\Service;
use App\Logs\Repository\LogSourceRepository;
use App\Shared\Doctrine\TimestampTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One container whose logs a service shows. Vector on the container's server sends them here.
 */
#[ORM\Entity(repositoryClass: LogSourceRepository::class)]
#[ORM\Table(name: '`log_sources`')]
#[ORM\UniqueConstraint(name: 'UNIQ_LOG_SOURCES_INGEST_TOKEN_HASH', fields: ['ingestTokenHash'])]
class LogSource
{
    use TimestampTrait;

    /** Null until the first log line arrives. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLogAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private Uuid $id,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Service $service,
        #[ORM\Column(length: 255)]
        private string $containerName,
        /** SHA-256 of the token Vector sends; see IngestToken. */
        #[ORM\Column(length: 64)]
        private string $ingestTokenHash,
        /** A label for people, e.g. "vps-1". Logs are matched by the source, not by host. */
        #[ORM\Column(length: 100, nullable: true)]
        private ?string $host = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getService(): Service
    {
        return $this->service;
    }

    public function getContainerName(): string
    {
        return $this->containerName;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function getLastLogAt(): ?\DateTimeImmutable
    {
        return $this->lastLogAt;
    }
}
