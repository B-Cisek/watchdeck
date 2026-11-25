<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ScheduleTest extends KernelTestCase
{
    public function testDueChecksAreRunEveryTenSeconds(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('debug:scheduler'));

        $tester->execute(['schedule' => ['default']]);

        self::assertMatchesRegularExpression('/every 10 seconds.*app:checks:run/', $tester->getDisplay());
    }
}
