<?php

declare(strict_types=1);

namespace App\Logs;

use App\Catalog\Entity\Service;
use App\Logs\Dto\AddLogSourceRequest;
use App\Logs\Entity\LogSource;
use App\Logs\Security\IngestToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class LogSourceCreator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function create(Service $service, AddLogSourceRequest $request): CreatedLogSource
    {
        $host = trim((string) $request->host);
        $token = IngestToken::generate();
        $source = new LogSource(Uuid::v7(), $service, $request->containerName, $token->hash(), $host === '' ? null : $host);

        $this->entityManager->persist($source);
        $this->entityManager->flush();

        return new CreatedLogSource($source, $token);
    }
}
