<?php

declare(strict_types=1);

namespace App\Monitoring\Dto;

use App\Monitoring\Enum\CheckType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCheckRequest
{
    /** @param array<string, mixed> $config validated by CheckConfigFactory, as its fields depend on the type */
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 100)]
        public string $name,
        public CheckType $type,
        public array $config,
        #[Assert\Range(min: 10, max: 86400)]
        public int $intervalSeconds = 60,
        #[Assert\Range(min: 1, max: 60)]
        public int $timeoutSeconds = 5,
    ) {
    }
}
