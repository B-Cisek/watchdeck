<?php

declare(strict_types=1);

namespace App\Monitoring\Entity;

use App\Monitoring\Repository\CheckResultRepository;
use App\Monitoring\Runner\CheckOutcome;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CheckResultRepository::class)]
#[ORM\Table(name: '`check_results`')]
#[ORM\Index(fields: ['check', 'checkedAt'])]
class CheckResult
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly Check $check,
        #[ORM\Column]
        private readonly bool $success,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $checkedAt,
        #[ORM\Column(nullable: true)]
        private readonly ?int $durationMs = null,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private readonly ?string $error = null,
        #[ORM\Column(type: Types::JSONB)]
        private readonly array $details = [],
    ) {}

    public static function fromOutcome(Check $check, CheckOutcome $outcome, \DateTimeImmutable $checkedAt): self
    {
        return new self($check, $outcome->success, $checkedAt, $outcome->durationMs, $outcome->error, $outcome->details);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /** @return array<string, mixed> */
    public function getDetails(): array
    {
        return $this->details;
    }
}
