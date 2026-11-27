<?php

declare(strict_types=1);

namespace App\Tests\Monitoring\Controller;

use App\Catalog\Entity\Service;
use App\Monitoring\Entity\Check;
use App\Monitoring\Entity\CheckResult;
use App\Monitoring\Enum\CheckType;
use App\Monitoring\Runner\CheckOutcome;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Uid\Uuid;

/**
 * @internal
 *
 * @coversNothing
 */
final class CheckControllerTest extends ApiTestCase
{
    private const string UNKNOWN_ID = '01935e5c-0000-7000-8000-000000000000';

    #[TestWith(['GET', '/api/checks'])]
    #[TestWith(['POST', '/api/services/'.self::UNKNOWN_ID.'/checks'])]
    #[TestWith(['PUT', '/api/checks/'.self::UNKNOWN_ID])]
    #[TestWith(['DELETE', '/api/checks/'.self::UNKNOWN_ID])]
    public function test_requires_authentication(string $method, string $url): void
    {
        $this->client->jsonRequest($method, $url);

        self::assertResponseStatusCodeSame(401);
    }

    public function test_creates_http_check_with_defaults(): void
    {
        $this->logIn();
        $service = $this->service();

        $this->create($service, ['name' => 'home', 'type' => 'http', 'config' => ['url' => 'https://shop.com']]);

        self::assertResponseStatusCodeSame(201);
        $payload = $this->json();
        // PostgreSQL's jsonb does not keep the key order.
        self::assertEquals([
            'id' => $payload['id'],
            'serviceId' => $service->getId()->toRfc4122(),
            'name' => 'home',
            'type' => 'http',
            'config' => ['url' => 'https://shop.com', 'method' => 'GET', 'expectedStatus' => 200],
            'intervalSeconds' => 60,
            'timeoutSeconds' => 5,
            'enabled' => true,
            'status' => 'pending',
            'lastCheckedAt' => null,
        ], $payload);

        $this->entityManager->clear();
        $check = $this->entityManager->find(Check::class, Uuid::fromString($payload['id']));
        self::assertSame('home', $check?->getName());
        self::assertEquals($payload['config'], $check->getConfig());
        self::assertTrue($check->getService()->getId()->equals($service->getId()));
    }

    public function test_creates_tcp_check_with_custom_timing(): void
    {
        $this->logIn();

        $this->create($this->service(), [
            'name' => 'database',
            'type' => 'tcp',
            'config' => ['host' => 'shop-db', 'port' => 5432],
            'intervalSeconds' => 300,
            'timeoutSeconds' => 10,
        ]);

        self::assertResponseStatusCodeSame(201);
        $payload = $this->json();
        self::assertSame('tcp', $payload['type']);
        self::assertEquals(['host' => 'shop-db', 'port' => 5432], $payload['config']);
        self::assertSame(300, $payload['intervalSeconds']);
        self::assertSame(10, $payload['timeoutSeconds']);
    }

    public function test_new_check_is_due_right_away(): void
    {
        $this->logIn();

        $this->create($this->service(), ['name' => 'home', 'type' => 'http', 'config' => ['url' => 'https://shop.com']]);

        self::assertCount(1, $this->entityManager->getRepository(Check::class)->findDue(new \DateTimeImmutable('+1 second')));
    }

    #[TestWith([self::UNKNOWN_ID])]
    #[TestWith(['nope'])]
    public function test_unknown_service_is_not_found(string $id): void
    {
        $this->logIn();

        $this->client->jsonRequest('POST', "/api/services/{$id}/checks", [
            'name' => 'home',
            'type' => 'http',
            'config' => ['url' => 'https://shop.com'],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidFields')]
    public function test_rejects_invalid_field(array $changes, string $propertyPath): void
    {
        $this->logIn();

        $this->create($this->service(), [
            ...['name' => 'home', 'type' => 'http', 'config' => ['url' => 'https://shop.com']],
            ...$changes,
        ]);

        self::assertResponseStatusCodeSame(422);
        $violations = $this->json()['violations'];
        self::assertSame([$propertyPath], array_column($violations, 'propertyPath'));
        self::assertNotSame('', $violations[0]['title']);
        self::assertSame(0, $this->entityManager->getRepository(Check::class)->count());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidFields(): iterable
    {
        yield 'whitespace name' => [['name' => '   '], 'name'];

        yield 'too long name' => [['name' => str_repeat('a', 101)], 'name'];

        yield 'unknown type' => [['type' => 'ping'], 'type'];

        yield 'interval below range' => [['intervalSeconds' => 9], 'intervalSeconds'];

        yield 'interval above range' => [['intervalSeconds' => 86401], 'intervalSeconds'];

        yield 'timeout below range' => [['timeoutSeconds' => 0], 'timeoutSeconds'];

        yield 'timeout above range' => [['timeoutSeconds' => 61], 'timeoutSeconds'];

        yield 'config not an object' => [['config' => 'https://shop.com'], 'config'];

        yield 'invalid config value' => [['config' => ['url' => 'shop']], 'config.url'];

        yield 'missing config value' => [['config' => []], 'config.url'];

        yield 'missing config value of tcp' => [['type' => 'tcp', 'config' => ['host' => 'shop-db']], 'config.port'];

        yield 'unknown config value' => [['config' => ['url' => 'https://shop.com', 'port' => 80]], 'config.port'];

        yield 'config value of wrong type' => [
            ['config' => ['url' => 'https://shop.com', 'expectedStatus' => '200']],
            'config.expectedStatus',
        ];
    }

    public function test_updates_check(): void
    {
        $this->logIn();
        $service = $this->service();
        $check = new Check(Uuid::v7(), $service, 'home', CheckType::HTTP, ['url' => 'https://shop.com']);
        $check->record(new CheckOutcome(true, 20), new \DateTimeImmutable('2026-11-25 10:00:00 UTC'));
        $this->persist($check);

        $this->update($check, [
            'name' => 'database',
            'type' => 'tcp',
            'config' => ['host' => 'shop-db', 'port' => 5432],
            'intervalSeconds' => 300,
            'timeoutSeconds' => 10,
        ]);

        self::assertResponseIsSuccessful();
        self::assertEquals([
            'id' => $check->getId()->toRfc4122(),
            'serviceId' => $service->getId()->toRfc4122(),
            'name' => 'database',
            'type' => 'tcp',
            'config' => ['host' => 'shop-db', 'port' => 5432],
            'intervalSeconds' => 300,
            'timeoutSeconds' => 10,
            'enabled' => true,
            'status' => 'pending',
            'lastCheckedAt' => null,
        ], $this->json());

        $this->entityManager->clear();
        $saved = $this->entityManager->find(Check::class, $check->getId());
        self::assertSame('database', $saved?->getName());
        self::assertEquals(['host' => 'shop-db', 'port' => 5432], $saved->getConfig());
    }

    public function test_update_fills_in_config_defaults(): void
    {
        $this->logIn();
        $check = new Check(Uuid::v7(), $this->service(), 'home', CheckType::HTTP, ['url' => 'https://shop.com']);
        $this->persist($check);

        $this->update($check, ['name' => 'home', 'type' => 'http', 'config' => ['url' => 'https://shop.com/health']]);

        self::assertResponseIsSuccessful();
        self::assertEquals(
            ['url' => 'https://shop.com/health', 'method' => 'GET', 'expectedStatus' => 200],
            $this->json()['config'],
        );
    }

    #[TestWith([self::UNKNOWN_ID])]
    #[TestWith(['nope'])]
    public function test_update_of_unknown_check_is_not_found(string $id): void
    {
        $this->logIn();

        $this->client->jsonRequest('PUT', "/api/checks/{$id}", [
            'name' => 'home',
            'type' => 'http',
            'config' => ['url' => 'https://shop.com'],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function test_update_rejects_invalid_config_and_keeps_the_check(): void
    {
        $this->logIn();
        $check = new Check(Uuid::v7(), $this->service(), 'home', CheckType::HTTP, ['url' => 'https://shop.com']);
        $this->persist($check);

        $this->update($check, ['name' => 'home', 'type' => 'tcp', 'config' => ['host' => 'shop-db']]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['config.port'], array_column($this->json()['violations'], 'propertyPath'));
        $this->entityManager->clear();
        self::assertSame(CheckType::HTTP, $this->entityManager->find(Check::class, $check->getId())?->getType());
    }

    public function test_creates_paused_check(): void
    {
        $this->logIn();

        $this->create($this->service(), [
            'name' => 'home',
            'type' => 'http',
            'config' => ['url' => 'https://shop.com'],
            'enabled' => false,
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertFalse($this->json()['enabled']);
        self::assertCount(0, $this->entityManager->getRepository(Check::class)->findDue(new \DateTimeImmutable('+1 second')));
    }

    public function test_update_pauses_and_resumes_check(): void
    {
        $this->logIn();
        $check = $this->persistCheck();
        $body = ['name' => 'home', 'type' => 'http', 'config' => ['url' => 'https://shop.com']];

        $this->update($check, [...$body, 'enabled' => false]);

        self::assertResponseIsSuccessful();
        self::assertFalse($this->json()['enabled']);
        $this->entityManager->clear();
        self::assertFalse($this->entityManager->find(Check::class, $check->getId())?->isEnabled());

        // Left out, it is enabled again, like the rest of a replaced check.
        $this->update($check, $body);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->json()['enabled']);
    }

    public function test_deletes_check_with_its_results(): void
    {
        $this->logIn();
        $check = $this->persistCheck();
        $other = $this->persistCheck();
        $this->persist(CheckResult::fromOutcome($check, new CheckOutcome(true), new \DateTimeImmutable()));
        $this->entityManager->clear();

        $this->client->request('DELETE', "/api/checks/{$check->getId()->toRfc4122()}");

        self::assertResponseStatusCodeSame(204);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Check::class, $check->getId()));
        self::assertNotNull($this->entityManager->find(Check::class, $other->getId()));
        self::assertSame(0, $this->entityManager->getRepository(CheckResult::class)->count());
    }

    public function test_delete_of_unknown_check_is_not_found(): void
    {
        $this->logIn();

        $this->client->request('DELETE', '/api/checks/'.self::UNKNOWN_ID);

        self::assertResponseStatusCodeSame(404);
    }

    public function test_lists_checks_by_name(): void
    {
        $this->logIn();
        $service = $this->service();
        $tcp = new Check(Uuid::v7(), $service, 'database', CheckType::TCP, ['host' => 'shop-db', 'port' => 5432]);
        $http = new Check(Uuid::v7(), $service, 'home', CheckType::HTTP, ['url' => 'https://shop.com']);
        $http->record(new CheckOutcome(true, 20), new \DateTimeImmutable('2026-11-25 10:00:00 UTC'));
        $this->persist($http, $tcp);
        $this->entityManager->clear();

        $this->client->request('GET', '/api/checks');

        self::assertResponseIsSuccessful();
        $checks = $this->json();
        self::assertSame(['database', 'home'], array_column($checks, 'name'));
        self::assertSame(['pending', 'up'], array_column($checks, 'status'));
        self::assertSame(
            array_fill(0, 2, $service->getId()->toRfc4122()),
            array_column($checks, 'serviceId'),
        );
        self::assertNull($checks[0]['lastCheckedAt']);
        self::assertEquals(
            new \DateTimeImmutable('2026-11-25 10:00:00 UTC'),
            new \DateTimeImmutable($checks[1]['lastCheckedAt']),
        );
    }

    private function service(): Service
    {
        $service = new Service(Uuid::v7(), 'shop');
        $this->persist($service);

        return $service;
    }

    private function persistCheck(): Check
    {
        $check = new Check(Uuid::v7(), $this->service(), 'home', CheckType::HTTP, ['url' => 'https://shop.com']);
        $this->persist($check);

        return $check;
    }

    /** @param array<string, mixed> $body */
    private function create(Service $service, array $body): void
    {
        $this->client->jsonRequest('POST', "/api/services/{$service->getId()->toRfc4122()}/checks", $body);
    }

    /** @param array<string, mixed> $body */
    private function update(Check $check, array $body): void
    {
        $this->client->jsonRequest('PUT', "/api/checks/{$check->getId()->toRfc4122()}", $body);
    }
}
