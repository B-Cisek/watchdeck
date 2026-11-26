<?php

declare(strict_types=1);

namespace App\Logs\Repository;

use App\Catalog\Entity\Service;
use App\Logs\Entity\LogSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LogSource>
 */
class LogSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogSource::class);
    }

    /** @return list<LogSource> */
    public function findOfService(Service $service): array
    {
        return $this->findBy(['service' => $service], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    /** Moves last_log_at forward only, as batches may arrive out of order. */
    public function touchLastLogAt(LogSource $source, \DateTimeImmutable $lastLogAt): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE log_sources SET last_log_at = GREATEST(last_log_at, :at) WHERE id = :id',
            ['at' => $lastLogAt->format('Y-m-d H:i:s'), 'id' => $source->getId()->toRfc4122()],
        );
    }
}
