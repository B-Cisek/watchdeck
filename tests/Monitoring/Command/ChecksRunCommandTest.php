<?php

declare(strict_types=1);

namespace App\Tests\Monitoring\Command;

use App\Monitoring\Entity\Check;
use App\Monitoring\Entity\CheckResult;
use App\Monitoring\Enum\CheckStatus;
use App\Tests\Monitoring\DatabaseTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Lock\LockFactory;

/**
 * @internal
 *
 * @coversNothing
 */
final class ChecksRunCommandTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHttp(new MockHttpClient(static fn () => new MockResponse('ok')));
    }

    public function test_due_check_is_executed_once_until_its_interval_passes(): void
    {
        $check = $this->persistCheck(['url' => 'https://shop.com/health']);

        $this->runDueChecks();
        $this->runDueChecks();

        self::assertSame(CheckStatus::UP, $this->reload($check)->getStatus());
        self::assertSame(1, $this->resultCount());
    }

    public function test_disabled_check_is_skipped(): void
    {
        $check = $this->persistCheck(['url' => 'https://shop.com/health']);
        $this->update($check, 'enabled', false);

        $this->runDueChecks();

        self::assertSame(0, $this->resultCount());
    }

    public function test_check_scheduled_in_the_future_is_skipped(): void
    {
        $check = $this->persistCheck(['url' => 'https://shop.com/health']);
        $this->update($check, 'nextCheckAt', new \DateTimeImmutable('+1 hour'));

        $this->runDueChecks();

        self::assertSame(0, $this->resultCount());
    }

    public function test_broken_config_does_not_stop_other_checks_and_is_not_a_failure(): void
    {
        $broken = $this->persistCheck([], name: 'broken');
        $this->update($broken, 'nextCheckAt', new \DateTimeImmutable('-1 hour')); // runs first
        $healthy = $this->persistCheck(['url' => 'https://shop.com/health'], name: 'healthy');

        $this->runDueChecks();

        self::assertSame(CheckStatus::UP, $this->reload($healthy)->getStatus());
        self::assertSame(CheckStatus::PENDING, $this->reload($broken)->getStatus());
        self::assertNull($this->reload($broken)->getLastCheckedAt());
        self::assertSame(1, $this->resultCount());
    }

    public function test_nothing_runs_while_another_run_holds_the_lock(): void
    {
        $this->persistCheck(['url' => 'https://shop.com/health']);
        $lock = self::getContainer()->get(LockFactory::class)->createLock('checks-run');
        self::assertTrue($lock->acquire());

        try {
            $this->runDueChecks();
        } finally {
            $lock->release();
        }

        self::assertSame(0, $this->resultCount());
    }

    private function runDueChecks(): void
    {
        $command = new Application(self::$kernel)->find('app:checks:run');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
    }

    private function update(Check $check, string $field, mixed $value): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(Check::class, 'c')
            ->set('c.'.$field, ':value')
            ->where('c.id = :id')
            ->setParameter('value', $value, $this->entityManager->getClassMetadata(Check::class)->getTypeOfField($field))
            ->setParameter('id', $check->getId(), 'uuid')
            ->getQuery()
            ->execute()
        ;

        $this->entityManager->clear();
    }

    private function reload(Check $check): Check
    {
        $this->entityManager->clear();

        return $this->entityManager->find(Check::class, $check->getId());
    }

    private function resultCount(): int
    {
        return $this->entityManager->getRepository(CheckResult::class)->count();
    }
}
