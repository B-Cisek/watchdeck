<?php

declare(strict_types=1);

namespace App\Logs;

use App\Catalog\Entity\Service;
use App\Logs\Dto\AddLogSourceRequest;
use App\Logs\Entity\LogSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class LogSourceCreator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function create(Service $service, AddLogSourceRequest $request): LogSource
    {
        $host = trim((string) $request->host);
        $source = new LogSource(Uuid::v7(), $service, $request->containerName, $host === '' ? null : $host);

        $this->entityManager->persist($source);
        $this->entityManager->flush();

        return $source;
    }
}
