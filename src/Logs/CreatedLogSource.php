<?php

declare(strict_types=1);

namespace App\Logs;

use App\Logs\Entity\LogSource;
use App\Logs\Security\IngestToken;

/** The token exists in full only here: the database keeps its hash. */
final readonly class CreatedLogSource
{
    public function __construct(
        public LogSource $source,
        public IngestToken $token,
    ) {}
}
