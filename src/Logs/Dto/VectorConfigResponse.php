<?php

declare(strict_types=1);

namespace App\Logs\Dto;

final readonly class VectorConfigResponse
{
    public function __construct(
        public string $fileName,
        public string $content,
    ) {}
}
