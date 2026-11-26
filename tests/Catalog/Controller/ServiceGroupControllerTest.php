<?php

declare(strict_types=1);

namespace App\Tests\Catalog\Controller;

use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServiceGroup;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class ServiceGroupControllerTest extends ApiTestCase
{
    private const string UNKNOWN_ID = '01935e5c-0000-7000-8000-000000000000';

    #[TestWith(['GET'])]
    #[TestWith(['POST'])]
    public function test_requires_authentication(string $method): void
    {
        $this->client->jsonRequest($method, '/api/service-groups', ['name' => 'production']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_creates_group(): void
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
    public function test_rejects_invalid_body(array $body): void
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

    public function test_rejects_form_data(): void
    {
        $this->logIn();

        $this->client->request('POST', '/api/service-groups', ['name' => 'production']);

        self::assertResponseStatusCodeSame(415);
    }

    public function test_lists_groups_by_name(): void
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

    public function test_list_is_empty_without_groups(): void
    {
        $this->logIn();

        $this->client->request('GET', '/api/service-groups');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json());
    }

    #[TestWith(['PUT'])]
    #[TestWith(['DELETE'])]
    public function test_changing_a_group_requires_authentication(string $method): void
    {
        $this->client->jsonRequest($method, '/api/service-groups/'.self::UNKNOWN_ID, ['name' => 'production']);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_renames_group(): void
    {
        $this->logIn();
        $group = new ServiceGroup(Uuid::v7(), 'production');
        $this->persist($group);
        $groupId = $group->getId()->toRfc4122();

        $this->client->jsonRequest('PUT', '/api/service-groups/'.$groupId, ['name' => 'live']);

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => $groupId, 'name' => 'live'], $this->json());

        $this->entityManager->clear();
        self::assertSame('live', $this->entityManager->find(ServiceGroup::class, $group->getId())?->getName());
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('invalidBodies')]
    public function test_rejects_invalid_rename(array $body): void
    {
        $this->logIn();
        $group = new ServiceGroup(Uuid::v7(), 'production');
        $this->persist($group);

        $this->client->jsonRequest('PUT', '/api/service-groups/'.$group->getId()->toRfc4122(), $body);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('name', $this->json()['violations'][0]['propertyPath']);
        $this->entityManager->clear();
        self::assertSame('production', $this->entityManager->find(ServiceGroup::class, $group->getId())?->getName());
    }

    public function test_deletes_group_and_keeps_its_services_without_a_group(): void
    {
        $this->logIn();
        $group = new ServiceGroup(Uuid::v7(), 'production');
        $shop = new Service(Uuid::v7(), 'shop', $group);
        $this->persist($group, $shop);
        $this->entityManager->clear();

        $this->client->request('DELETE', '/api/service-groups/'.$group->getId()->toRfc4122());

        self::assertResponseStatusCodeSame(204);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(ServiceGroup::class, $group->getId()));
        $service = $this->entityManager->find(Service::class, $shop->getId());
        self::assertNotNull($service);
        self::assertNull($service->getGroup());
    }

    #[TestWith(['PUT'])]
    #[TestWith(['DELETE'])]
    public function test_unknown_group_is_not_found(string $method): void
    {
        $this->logIn();

        $this->client->jsonRequest($method, '/api/service-groups/'.self::UNKNOWN_ID, ['name' => 'live']);

        self::assertResponseStatusCodeSame(404);
    }
}
