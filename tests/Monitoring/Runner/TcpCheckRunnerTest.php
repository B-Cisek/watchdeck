<?php

declare(strict_types=1);

namespace App\Tests\Monitoring\Runner;

use App\Monitoring\Config\CheckConfigFactory;
use App\Monitoring\Enum\CheckType;
use App\Monitoring\Runner\TcpCheckRunner;
use App\Tests\Monitoring\CheckFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class TcpCheckRunnerTest extends KernelTestCase
{
    private TcpCheckRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new TcpCheckRunner(self::getContainer()->get(CheckConfigFactory::class));
    }

    public function test_open_port_is_success(): void
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

    public function test_closed_port_is_failure(): void
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
