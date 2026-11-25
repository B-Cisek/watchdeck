<?php

declare(strict_types=1);

namespace App\Monitoring\Runner;

final readonly class CheckOutcome
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public bool $success,
        public ?int $durationMs = null,
        public ?string $error = null,
        public array $details = [],
    ) {}
}
