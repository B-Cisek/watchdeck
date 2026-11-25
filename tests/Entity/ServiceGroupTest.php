<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Service;
use App\Entity\ServiceGroup;
use App\Tests\DatabaseTestCase;
use Symfony\Component\Uid\Uuid;

final class ServiceGroupTest extends DatabaseTestCase
{
    /** Doctrine cannot initialize a lazy entity whose id is a readonly object property. */
    public function testGroupCanBeLazyLoadedThroughItsService(): void
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
