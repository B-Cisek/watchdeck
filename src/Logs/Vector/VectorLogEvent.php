<?php

declare(strict_types=1);

namespace App\Logs\Vector;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One event from Vector's docker_logs source, with the fields the generated config sends.
 */
final readonly class VectorLogEvent
{
    public function __construct(
        #[SerializedName('container_id')]
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $containerId,
        #[SerializedName('container_name')]
        #[Assert\NotBlank]
        public string $containerName,
        #[Assert\Choice(choices: ['stdout', 'stderr'])]
        public string $stream,
        /** Kept as sent: it has nanoseconds, which PHP cuts to microseconds. */
        #[Assert\NotBlank]
        #[Assert\Regex('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,9})?(Z|[+-]\d{2}:\d{2})$/', message: 'Expected an RFC 3339 timestamp.')]
        public string $timestamp,
        public string $message,
    ) {}
}
