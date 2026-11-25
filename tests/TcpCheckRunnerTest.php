<?php

declare(strict_types=1);

namespace App\Tests;

use App\CheckConfigFactory;
use App\Enum\CheckType;
use App\TcpCheckRunner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TcpCheckRunnerTest extends KernelTestCase
{
    private TcpCheckRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new TcpCheckRunner(self::getContainer()->get(CheckConfigFactory::class));
    }

    public function testOpenPortIsSuccess(): void
    {
        [$server, $port] = $this->listen();

        $outcome = $this->runner->run(
            CheckFactory::create(['host' => '127.0.0.1', 'port' => $port], CheckType::TCP),
        );
        fclose($server);

        self::assertTrue($outcome->success);
        self::assertNull($outcome->error);
        self::assertGreaterThanOrEqual(0, $outcome->durationMs);
    }

    public function testClosedPortIsFailure(): void
    {
        [$server, $port] = $this->listen();
        fclose($server);

        $outcome = $this->runner->run(
            CheckFactory::create(['host' => '127.0.0.1', 'port' => $port], CheckType::TCP),
        );

        self::assertFalse($outcome->success);
        self::assertSame('Connection refused', $outcome->error);
    }

    /** @return array{resource, int} */
    private function listen(): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($server);

        $address = stream_socket_get_name($server, false);

        return [$server, (int) substr(strrchr($address, ':'), 1)];
    }
}
