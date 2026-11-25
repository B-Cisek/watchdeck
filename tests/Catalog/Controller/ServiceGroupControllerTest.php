<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Controller;

use App\Catalog\Entity\ServiceGroup;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Uid\Uuid;

final class ServiceGroupControllerTest extends ApiTestCase
{
    #[TestWith(['GET'])]
    #[TestWith(['POST'])]
    public function testRequiresAuthentication(string $method): void
    {
        $this->client->jsonRequest($method, '/api/service-groups', ['name' => 'production']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreatesGroup(): void
    {
        $this->logIn();

        $this->client->jsonRequest('POST', '/api/service-groups', ['name' => 'production']);

        self::assertResponseStatusCodeSame(201);
        $payload = $this->json();
        self::assertSame(['id', 'name'], array_keys($payload));
        self::assertSame('production', $payload['name']);

        $this->entityManager->clear();
        $group = $this->entityManager->find(ServiceGroup::class, Uuid::fromString($payload['id']));
        self::assertSame('production', $group?->getName());
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('invalidBodies')]
    public function testRejectsInvalidBody(array $body): void
    {
        $this->logIn();

        $this->client->jsonRequest('POST', '/api/service-groups', $body);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('name', $this->json()['violations'][0]['propertyPath']);
        self::assertSame(0, $this->entityManager->getRepository(ServiceGroup::class)->count());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidBodies(): iterable
    {
        yield 'missing name' => [[]];
        yield 'whitespace name' => [['name' => '   ']];
        yield 'too long name' => [['name' => str_repeat('a', 101)]];
        yield 'name of wrong type' => [['name' => ['production']]];
    }

    public function testRejectsFormData(): void
    {
        $this->logIn();

        $this->client->request('POST', '/api/service-groups', ['name' => 'production']);

        self::assertResponseStatusCodeSame(415);
    }

    public function testListsGroupsByName(): void
    {
        $this->logIn();
        $staging = new ServiceGroup(Uuid::v7(), 'staging');
        $production = new ServiceGroup(Uuid::v7(), 'production');
        $this->persist($staging, $production);

        $this->client->request('GET', '/api/service-groups');

        self::assertResponseIsSuccessful();
        self::assertSame([
            ['id' => $production->getId()->toRfc4122(), 'name' => 'production'],
            ['id' => $staging->getId()->toRfc4122(), 'name' => 'staging'],
        ], $this->json());
    }

    public function testListIsEmptyWithoutGroups(): void
    {
        $this->logIn();

        $this->client->request('GET', '/api/service-groups');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json());
    }
}
