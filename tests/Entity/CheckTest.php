<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\CheckOutcome;
use App\Enum\CheckStatus;
use App\Tests\CheckFactory;
use PHPUnit\Framework\TestCase;

final class CheckTest extends TestCase
{
    public function testNewCheckIsPendingAndDueImmediately(): void
    {
        $check = CheckFactory::create([]);

        self::assertSame(CheckStatus::PENDING, $check->getStatus());
        self::assertNull($check->getLastCheckedAt());
        self::assertLessThanOrEqual(new \DateTimeImmutable(), $check->getNextCheckAt());
    }

    public function testSuccessMakesCheckUp(): void
    {
        $check = CheckFactory::create([]);

        $check->record(new CheckOutcome(true), new \DateTimeImmutable());

        self::assertSame(CheckStatus::UP, $check->getStatus());
    }

    public function testSingleFailureDoesNotChangeStatus(): void
    {
        $check = CheckFactory::create([]);
        $check->record(new CheckOutcome(true), new \DateTimeImmutable());

        $check->record(new CheckOutcome(false), new \DateTimeImmutable());

        self::assertSame(CheckStatus::UP, $check->getStatus());
        self::assertSame(1, $check->getConsecutiveFailures());
    }

    public function testSecondFailureInARowMakesCheckDown(): void
    {
        $check = CheckFactory::create([]);

        $check->record(new CheckOutcome(false), new \DateTimeImmutable());
        self::assertSame(CheckStatus::PENDING, $check->getStatus());

        $check->record(new CheckOutcome(false), new \DateTimeImmutable());
        self::assertSame(CheckStatus::DOWN, $check->getStatus());
    }

    public function testSuccessBetweenFailuresResetsTheCounter(): void
    {
        $check = CheckFactory::create([]);

        $check->record(new CheckOutcome(false), new \DateTimeImmutable());
        $check->record(new CheckOutcome(true), new \DateTimeImmutable());
        $check->record(new CheckOutcome(false), new \DateTimeImmutable());

        self::assertSame(CheckStatus::UP, $check->getStatus());
        self::assertSame(1, $check->getConsecutiveFailures());
    }

    public function testDownCheckRecoversAfterOneSuccess(): void
    {
        $check = CheckFactory::create([]);
        $check->record(new CheckOutcome(false), new \DateTimeImmutable());
        $check->record(new CheckOutcome(false), new \DateTimeImmutable());

        $check->record(new CheckOutcome(true), new \DateTimeImmutable());

        self::assertSame(CheckStatus::UP, $check->getStatus());
        self::assertSame(0, $check->getConsecutiveFailures());
    }

    public function testRecordSchedulesNextCheckAfterTheInterval(): void
    {
        $check = CheckFactory::create([], intervalSeconds: 300);

        $check->record(new CheckOutcome(true), new \DateTimeImmutable('2026-11-25 12:00:00'));

        self::assertEquals(new \DateTimeImmutable('2026-11-25 12:00:00'), $check->getLastCheckedAt());
        self::assertEquals(new \DateTimeImmutable('2026-11-25 12:05:00'), $check->getNextCheckAt());
    }
}
