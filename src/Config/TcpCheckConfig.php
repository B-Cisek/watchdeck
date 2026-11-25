<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TcpCheckConfig
{
    public function __construct(
        #[Assert\NotBlank]
        public string $host,
        #[Assert\Range(min: 1, max: 65535)]
        public int $port,
    ) {}
}
