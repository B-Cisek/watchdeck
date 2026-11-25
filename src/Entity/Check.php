<?php

declare(strict_types=1);

namespace App\Entity;

use App\CheckOutcome;
use App\Enum\CheckStatus;
use App\Enum\CheckType;
use App\Repository\CheckRepository;
use App\TimestampTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CheckRepository::class)]
#[ORM\Table(name: '`checks`')]
#[ORM\Index(fields: ['enabled', 'nextCheckAt'])]
class Check
{
    use TimestampTrait;

    private const int FAILURES_TO_DOWN = 2;

    #[ORM\Column(length: 20, enumType: CheckStatus::class)]
    private CheckStatus $status = CheckStatus::PENDING;

    #[ORM\Column]
    private int $consecutiveFailures = 0;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastCheckedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $nextCheckAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME, unique: true)]
        private Uuid $id,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Service $service,
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\Column(length: 20, enumType: CheckType::class)]
        private CheckType $type,
        #[ORM\Column(type: Types::JSONB)]
        private array $config,
        #[ORM\Column]
        private int $intervalSeconds = 60,
        #[ORM\Column]
        private int $timeoutSeconds = 5,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->nextCheckAt = $this->createdAt;
    }

    public function record(CheckOutcome $outcome, \DateTimeImmutable $checkedAt): void
    {
        $this->lastCheckedAt = $checkedAt;
        $this->nextCheckAt = $checkedAt->modify(sprintf('+%d seconds', $this->intervalSeconds));

        if ($outcome->success) {
            $this->consecutiveFailures = 0;
            $this->status = CheckStatus::UP;

            return;
        }

        ++$this->consecutiveFailures;

        if ($this->consecutiveFailures >= self::FAILURES_TO_DOWN) {
            $this->status = CheckStatus::DOWN;
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getService(): Service
    {
        return $this->service;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): CheckType
    {
        return $this->type;
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function getIntervalSeconds(): int
    {
        return $this->intervalSeconds;
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function getStatus(): CheckStatus
    {
        return $this->status;
    }

    public function getConsecutiveFailures(): int
    {
        return $this->consecutiveFailures;
    }

    public function getLastCheckedAt(): ?\DateTimeImmutable
    {
        return $this->lastCheckedAt;
    }

    public function getNextCheckAt(): \DateTimeImmutable
    {
        return $this->nextCheckAt;
    }
}
