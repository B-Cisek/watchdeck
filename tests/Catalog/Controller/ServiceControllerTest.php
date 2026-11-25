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
final class ServiceControllerTest extends ApiTestCase
{
    #[TestWith(['GET'])]
    #[TestWith(['POST'])]
    public function test_requires_authentication(string $method): void
    {
        $this->client->jsonRequest($method, '/api/services', ['name' => 'shop']);

        self::assertResponseStatusCodeSame(401);
    }

    /** @param array<string, mixed> $body */
    #[TestWith([['name' => 'shop']])]
    #[TestWith([['name' => 'shop', 'groupId' => null]])]
    public function test_creates_service_without_group(array $body): void
    {
        $this->logIn();

        $this->client->jsonRequest('POST', '/api/services', $body);

        self::assertResponseStatusCodeSame(201);
        $payload = $this->json();
        self::assertSame(['id', 'name', 'group'], array_keys($payload));
        self::assertSame('shop', $payload['name']);
        self::assertNull($payload['group']);

        $this->entityManager->clear();
        $service = $this->entityManager->find(Service::class, Uuid::fromString($payload['id']));
        self::assertSame('shop', $service?->getName());
        self::assertNull($service->getGroup());
    }

    public function test_creates_service_in_group(): void
    {
        $this->logIn();
        $group = new ServiceGroup(Uuid::v7(), 'production');
        $this->persist($group);
        $groupId = $group->getId()->toRfc4122();

        $this->client->jsonRequest('POST', '/api/services', ['name' => 'shop', 'groupId' => $groupId]);

        self::assertResponseStatusCodeSame(201);
        $payload = $this->json();
        self::assertSame(['id' => $groupId, 'name' => 'production'], $payload['group']);

        $this->entityManager->clear();
        $service = $this->entityManager->find(Service::class, Uuid::fromString($payload['id']));
        self::assertSame($groupId, $service?->getGroup()?->getId()->toRfc4122());
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('invalidBodies')]
    public function test_rejects_invalid_body(array $body, string $propertyPath): void
    {
        $this->logIn();

        $this->client->jsonRequest('POST', '/api/services', $body);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($propertyPath, $this->json()['violations'][0]['propertyPath']);
        self::assertSame(0, $this->entityManager->getRepository(Service::class)->count());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidBodies(): iterable
    {
        yield 'missing name' => [[], 'name'];

        yield 'whitespace name' => [['name' => '   '], 'name'];

        yield 'too long name' => [['name' => str_repeat('a', 101)], 'name'];

        yield 'malformed group id' => [['name' => 'shop', 'groupId' => 'nope'], 'groupId'];

        yield 'unknown group' => [['name' => 'shop', 'groupId' => '01935e5c-0000-7000-8000-000000000000'], 'groupId'];
    }

    #[TestWith(['nope', 'This is not a valid UUID.'])]
    #[TestWith(['', 'This value should not be blank.'])]
    #[TestWith([1, 'This value should be of type null|string.'])]
    #[TestWith(['01935e5c-0000-7000-8000-000000000000', 'Service group not found.'])]
    public function test_explains_why_group_id_is_invalid(mixed $groupId, string $message): void
    {
        $this->logIn();

        $this->client->jsonRequest('POST', '/api/services', ['name' => 'shop', 'groupId' => $groupId]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            [['groupId', $message]],
            array_map(static fn (array $v) => [$v['propertyPath'], $v['title']], $this->json()['violations']),
        );
    }

    public function test_lists_services_by_name_with_their_group(): void
    {
        $this->logIn();
        $group = new ServiceGroup(Uuid::v7(), 'production');
        $shop = new Service(Uuid::v7(), 'shop', $group);
        $blog = new Service(Uuid::v7(), 'blog');
        $this->persist($group, $shop, $blog);
        // Otherwise the group would come from the identity map instead of the query.
        $this->entityManager->clear();

        $this->client->request('GET', '/api/services');

        self::assertResponseIsSuccessful();
        self::assertSame([
            ['id' => $blog->getId()->toRfc4122(), 'name' => 'blog', 'group' => null],
            [
                'id' => $shop->getId()->toRfc4122(),
                'name' => 'shop',
                'group' => ['id' => $group->getId()->toRfc4122(), 'name' => 'production'],
            ],
        ], $this->json());
    }
}
