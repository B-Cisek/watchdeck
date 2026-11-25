<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Entity;

use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServiceGroup;
use App\Tests\Monitoring\DatabaseTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class ServiceGroupTest extends DatabaseTestCase
{
    /** Doctrine cannot initialize a lazy entity whose id is a readonly object property. */
    public function test_group_can_be_lazy_loaded_through_its_service(): void
    {
        $group = new ServiceGroup(Uuid::v7(), 'production');
        $service = new Service(Uuid::v7(), 'shop', $group);
        $this->entityManager->persist($group);
        $this->entityManager->persist($service);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(Service::class, $service->getId());

        self::assertSame('production', $reloaded->getGroup()?->getName());
    }
}
