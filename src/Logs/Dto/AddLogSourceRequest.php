<?php

declare(strict_types=1);

namespace App\Logs\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddLogSourceRequest
{
    public function __construct(
        // Docker's own rule for container names. It also keeps the name safe inside the generated Vector condition.
        #[Assert\NotBlank]
        #[Assert\Regex('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', message: 'Use a Docker container name: letters, digits, "_", "." and "-".')]
        #[Assert\Length(max: 255)]
        public string $containerName,
        #[Assert\Length(max: 100)]
        public ?string $host = null,
    ) {}
}
