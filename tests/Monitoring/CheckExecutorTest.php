<?php

declare(strict_types=1);

namespace App\Tests\Monitoring;

use App\Monitoring\CheckExecutor;
use App\Monitoring\Entity\Check;
use App\Monitoring\Entity\CheckResult;
use App\Monitoring\Enum\CheckStatus;
use App\Monitoring\Enum\CheckType;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 *
 * @coversNothing
 */
final class CheckExecutorTest extends DatabaseTestCase
{
    public function test_successful_http_check_is_stored(): void
    {
        $this->mockHttp(new MockHttpClient(new MockResponse('ok')));
        $check = $this->persistCheck(['url' => 'https://shop.com/health']);

        self::getContainer()->get(CheckExecutor::class)->execute($check);

        $this->entityManager->clear();
        $result = $this->onlyResultOf($check);
        self::assertTrue($result->isSuccess());
        self::assertNull($result->getError());
        self::assertSame(['statusCode' => 200], $result->getDetails());

        $reloaded = $this->entityManager->find(Check::class, $check->getId());
        self::assertSame(CheckStatus::UP, $reloaded->getStatus());
        self::assertNotNull($reloaded->getLastCheckedAt());
        self::assertSame(['url' => 'https://shop.com/health'], $reloaded->getConfig());
    }

    public function test_failure_is_stored_and_second_one_makes_check_down(): void
    {
        $this->mockHttp(new MockHttpClient(static fn () => new MockResponse('', ['http_code' => 503])));
        $check = $this->persistCheck(['url' => 'https://shop.com/health']);
        $executor = self::getContainer()->get(CheckExecutor::class);

        $result = $executor->execute($check);
        self::assertFalse($result->isSuccess());
        self::assertSame('Expected HTTP 200, got 503', $result->getError());
        self::assertSame(CheckStatus::PENDING, $check->getStatus());

        $executor->execute($check);

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Check::class, $check->getId());
        self::assertSame(CheckStatus::DOWN, $reloaded->getStatus());
        self::assertSame(2, $this->entityManager->getRepository(CheckResult::class)->count(['check' => $reloaded]));
    }

    public function test_every_check_type_has_a_runner(): void
    {
        $this->mockHttp(new MockHttpClient(new MockResponse('ok')));
        $executor = self::getContainer()->get(CheckExecutor::class);

        $configs = [
            CheckType::HTTP->value => ['url' => 'https://shop.com/health'],
            CheckType::TCP->value => ['host' => '127.0.0.1', 'port' => 1],
        ];

        foreach (CheckType::cases() as $type) {
            self::assertArrayHasKey($type->value, $configs, 'Add a sample config for the new check type.');

            $check = $this->persistCheck($configs[$type->value], $type);
            $executor->execute($check);

            self::assertNotNull($check->getLastCheckedAt());
        }
    }

    public function test_deleting_check_deletes_its_results(): void
    {
        $this->mockHttp(new MockHttpClient(new MockResponse('ok')));
        $check = $this->persistCheck(['url' => 'https://shop.com/health']);
        self::getContainer()->get(CheckExecutor::class)->execute($check);
        $this->entityManager->clear();

        $this->entityManager->remove($this->entityManager->find(Check::class, $check->getId()));
        $this->entityManager->flush();

        self::assertSame(0, $this->entityManager->getRepository(CheckResult::class)->count());
    }

    private function onlyResultOf(Check $check): CheckResult
    {
        $results = $this->entityManager->getRepository(CheckResult::class)->findBy(['check' => $check->getId()]);
        self::assertCount(1, $results);

        return $results[0];
    }
}
