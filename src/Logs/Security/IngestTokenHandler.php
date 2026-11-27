<?php

declare(strict_types=1);

namespace App\Logs\Security;

use App\Logs\Repository\LogSourceRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class IngestTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private LogSourceRepository $sources) {}

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $source = $this->sources->findOneBy(['ingestTokenHash' => new IngestToken($accessToken)->hash()])
            ?? throw new BadCredentialsException('Invalid token.');

        // Own loader, or the firewall would look the identifier up as a User.
        return new UserBadge(
            userIdentifier: $source->getId()->toRfc4122(),
            userLoader: static fn () => new IngestClient($source),
        );
    }
}
