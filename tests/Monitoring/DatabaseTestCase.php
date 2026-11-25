<?php

declare(strict_types=1);

namespace App\Tests\Monitoring;

use App\Catalog\Entity\Service;
use App\Monitoring\Entity\Check;
use App\Monitoring\Enum\CheckType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Uid\Uuid;

/**
 * Runs every test in a transaction that is rolled back, so tests never see each other's rows.
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollBack();
        $this->entityManager->close();

        parent::tearDown();
    }

    /** Must be called before the first service that uses the HTTP client is fetched. */
    protected function mockHttp(MockHttpClient $client): void
    {
        self::getContainer()->set('http_client', $client);
    }

    /** @param array<string, mixed> $config */
    protected function persistCheck(array $config, CheckType $type = CheckType::HTTP, string $name = 'check'): Check
    {
        $service = new Service(Uuid::v7(), 'shop');
        $check = new Check(Uuid::v7(), $service, $name, $type, $config);

        $this->entityManager->persist($service);
        $this->entityManager->persist($check);
        $this->entityManager->flush();

        return $check;
    }
}
