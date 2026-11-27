<?php

declare(strict_types=1);

namespace App\Logs\Security;

/**
 * The secret Vector sends as a Bearer token. Only its hash is stored, so the full value is
 * shown once, in the Vector config returned when the source is added.
 */
final readonly class IngestToken
{
    public function __construct(
        public string $value,
    ) {}

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(32)));
    }

    /** Plain SHA-256 is enough: the token is 32 random bytes, not a password to brute-force. */
    public function hash(): string
    {
        return hash('sha256', $this->value);
    }
}
