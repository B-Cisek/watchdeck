<?php

declare(strict_types=1);

namespace App\Tests\Monitoring\Entity;

use App\Monitoring\Enum\CheckStatus;
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

    public function test_record_schedules_next_check_after_the_interval(): void
    {
        $check = CheckFactory::create([], intervalSeconds: 300);

        $check->record(new CheckOutcome(true), new \DateTimeImmutable('2026-11-25 12:00:00'));

        self::assertEquals(new \DateTimeImmutable('2026-11-25 12:00:00'), $check->getLastCheckedAt());
        self::assertEquals(new \DateTimeImmutable('2026-11-25 12:05:00'), $check->getNextCheckAt());
    }
}
