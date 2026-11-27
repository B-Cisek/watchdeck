<?php

declare(strict_types=1);

namespace App\Tests\Monitoring\Entity;

use App\Monitoring\Enum\CheckStatus;
use App\Monitoring\Enum\CheckType;
use App\Monitoring\Runner\CheckOutcome;
use App\Tests\Monitoring\CheckFactory;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CheckTest extends TestCase
{
    public function test_new_check_is_pending_and_due_immediately(): void
    {
        $check = CheckFactory::create([]);

        self::assertSame(CheckStatus::PENDING, $check->getStatus());
        self::assertNull($check->getLastCheckedAt());
        self::assertLessThanOrEqual(new \DateTimeImmutable(), $check->getNextCheckAt());
    }

    public function test_success_makes_check_up(): void
    {
        $check = CheckFactory::create([]);

        $check->record(new CheckOutcome(true), new \DateTimeImmutable());

        self::assertSame(CheckStatus::UP, $check->getStatus());
    }

    public function test_single_failure_does_not_change_status(): void
    {
        $check = CheckFactory::create([]);
        $check->record(new CheckOutcome(true), new \DateTimeImmutable());

        $check->record(new CheckOutcome(false), new \DateTimeImmutable());

        self::assertSame(CheckStatus::UP, $check->getStatus());
        self::assertSame(1, $check->getConsecutiveFailures());
    }

    public function test_second_failure_in_a_row_makes_check_down(): void
    {
        $check = CheckFactory::create([]);

        $check->record(new CheckOutcome(false), new \DateTimeImmutable());
        self::assertSame(CheckStatus::PENDING, $check->getStatus());

        $check->record(new CheckOutcome(false), new \DateTimeImmutable());
        self::assertSame(CheckStatus::DOWN, $check->getStatus());
    }

    public function test_success_between_failures_resets_the_counter(): void
    {
        $check = CheckFactory::create([]);

        $check->record(new CheckOutcome(false), new \DateTimeImmutable());
        $check->record(new CheckOutcome(true), new \DateTimeImmutable());
        $check->record(new CheckOutcome(false), new \DateTimeImmutable());

        self::assertSame(CheckStatus::UP, $check->getStatus());
        self::assertSame(1, $check->getConsecutiveFailures());
    }

    public function test_down_check_recovers_after_one_success(): void
    {
        $check = CheckFactory::create([]);
        $check->record(new CheckOutcome(false), new \DateTimeImmutable());
        $check->record(new CheckOutcome(false), new \DateTimeImmutable());

        $check->record(new CheckOutcome(true), new \DateTimeImmutable());

        self::assertSame(CheckStatus::UP, $check->getStatus());
        self::assertSame(0, $check->getConsecutiveFailures());
    }

    public function test_new_target_starts_over_as_pending_and_due(): void
    {
        $check = CheckFactory::create(['url' => 'https://shop.com']);
        $check->record(new CheckOutcome(false), new \DateTimeImmutable('2026-11-25 12:00:00'));
        $check->record(new CheckOutcome(false), new \DateTimeImmutable('2026-11-25 12:01:00'));

        $check->update('db', CheckType::TCP, ['host' => 'shop-db', 'port' => 5432], 60, 5);

        self::assertSame(CheckStatus::PENDING, $check->getStatus());
        self::assertSame(0, $check->getConsecutiveFailures());
        self::assertNull($check->getLastCheckedAt());
        self::assertLessThanOrEqual(new \DateTimeImmutable(), $check->getNextCheckAt());
    }

    public function test_same_target_in_another_key_order_keeps_the_status(): void
    {
        $check = CheckFactory::create(['url' => 'https://shop.com', 'method' => 'GET']);
        $check->record(new CheckOutcome(true), new \DateTimeImmutable('2026-11-25 12:00:00'));

        $check->update('renamed', CheckType::HTTP, ['method' => 'GET', 'url' => 'https://shop.com'], 60, 10);

        self::assertSame('renamed', $check->getName());
        self::assertSame(10, $check->getTimeoutSeconds());
        self::assertSame(CheckStatus::UP, $check->getStatus());
        self::assertEquals(new \DateTimeImmutable('2026-11-25 12:01:00'), $check->getNextCheckAt());
    }

    public function test_new_interval_moves_the_next_check(): void
    {
        $check = CheckFactory::create(['url' => 'https://shop.com']);
        $check->record(new CheckOutcome(true), new \DateTimeImmutable('2026-11-25 12:00:00'));

        $check->update('check', CheckType::HTTP, ['url' => 'https://shop.com'], 300, 5);

        self::assertSame(CheckStatus::UP, $check->getStatus());
        self::assertEquals(new \DateTimeImmutable('2026-11-25 12:05:00'), $check->getNextCheckAt());
    }

    public function test_resumed_check_is_due_right_away(): void
    {
        $check = CheckFactory::create([]);
        $check->record(new CheckOutcome(true), new \DateTimeImmutable());
        $check->setEnabled(false);

        $check->setEnabled(true);

        self::assertTrue($check->isEnabled());
        self::assertLessThanOrEqual(new \DateTimeImmutable(), $check->getNextCheckAt());
    }

    public function test_enabling_an_enabled_check_keeps_its_schedule(): void
    {
        $check = CheckFactory::create([]);
        $check->record(new CheckOutcome(true), new \DateTimeImmutable('2026-11-25 12:00:00'));

        $check->setEnabled(true);

        self::assertEquals(new \DateTimeImmutable('2026-11-25 12:01:00'), $check->getNextCheckAt());
    }

    public function test_record_schedules_next_check_after_the_interval(): void
    {
        $check = CheckFactory::create([], intervalSeconds: 300);

        $check->record(new CheckOutcome(true), new \DateTimeImmutable('2026-11-25 12:00:00'));

        self::assertEquals(new \DateTimeImmutable('2026-11-25 12:00:00'), $check->getLastCheckedAt());
        self::assertEquals(new \DateTimeImmutable('2026-11-25 12:05:00'), $check->getNextCheckAt());
    }
}
