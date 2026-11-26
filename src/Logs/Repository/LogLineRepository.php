<?php

declare(strict_types=1);

namespace App\Logs\Repository;

use App\Catalog\Entity\Service;
use App\Logs\Entity\LogLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LogLine>
 */
class LogLineRepository extends ServiceEntityRepository
{
    /** 6 parameters per row, well under PostgreSQL's limit of 65535 per statement. */
    private const int ROWS_PER_INSERT = 1000;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LogLine::class);
    }

    /**
     * The newest lines of all the service's sources, oldest first, as a terminal shows them.
     *
     * @return list<LogLine>
     */
    public function findLatestOfService(Service $service, int $limit): array
    {
        /** @var list<LogLine> $lines */
        $lines = $this->createQueryBuilder('l')
            ->addSelect('s')
            ->join('l.logSource', 's')
            ->andWhere('s.service = :service')
            ->setParameter('service', $service->getId(), 'uuid')
            ->orderBy('l.loggedAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return array_reverse($lines);
    }

    /**
     * Plain SQL because the ORM would throw on a duplicate event_hash and close the entity manager.
     *
     * @param list<array{log_source_id: string, docker_id: string, logged_at: string, stream: string, message: string, event_hash: string}> $rows
     *
     * @return int rows inserted, duplicates excluded
     */
    public function insertIgnoringDuplicates(array $rows): int
    {
        $connection = $this->getEntityManager()->getConnection();
        $inserted = 0;

        foreach (array_chunk($rows, self::ROWS_PER_INSERT) as $chunk) {
            $values = implode(', ', array_fill(0, \count($chunk), '(?, ?, ?, ?, ?, ?)'));
            $inserted += (int) $connection->executeStatement(
                "INSERT INTO log_lines (log_source_id, docker_id, logged_at, stream, message, event_hash)
                 VALUES {$values}
                 ON CONFLICT (event_hash) DO NOTHING",
                array_merge(...array_map(array_values(...), $chunk)),
            );
        }

        return $inserted;
    }
}
