<?php

declare(strict_types=1);

namespace App\Monitoring\Config;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class HttpCheckConfig
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Url(protocols: ['http', 'https'], requireTld: false)]
        public string $url,
        #[Assert\Choice(choices: ['GET', 'HEAD'])]
        public string $method = 'GET',
        #[Assert\Range(min: 100, max: 599)]
        public int $expectedStatus = 200,
    ) {}
}
