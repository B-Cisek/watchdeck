<?php

namespace App\Repository;

use App\Entity\Check;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Check>
 */
class CheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Check::class);
    }

    /** @return list<Check> */
    public function findDue(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.enabled = true')
            ->andWhere('c.nextCheckAt <= :now')
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->orderBy('c.nextCheckAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Check[] Returns an array of Check objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Check
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
