<?php

declare(strict_types=1);

namespace App\Logs\Security;

use App\Logs\Entity\LogSource;
use Symfony\Component\Security\Core\User\UserInterface;

/** Vector, signed in on the ingest firewall with the token of one log source. */
final readonly class IngestClient implements UserInterface
{
    public function __construct(public LogSource $source) {}

    public function getRoles(): array
    {
        return ['ROLE_INGEST'];
    }

    public function getUserIdentifier(): string
    {
        return $this->source->getId()->toRfc4122();
    }
}
