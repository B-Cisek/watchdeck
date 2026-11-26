<?php

declare(strict_types=1);

namespace App\Logs\Dto;

final readonly class AddedLogSourceResponse
{
    public function __construct(
        public LogSourceResponse $logSource,
        public VectorConfigResponse $vectorConfig,
    ) {}
}
