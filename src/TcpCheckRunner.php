<?php

declare(strict_types=1);

namespace App;

use App\Config\TcpCheckConfig;
use App\Entity\Check;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsTaggedItem('tcp')]
final readonly class TcpCheckRunner implements CheckRunnerInterface
{
    public function __construct(
        private CheckConfigFactory $configFactory,
    ) {}

    public function run(Check $check): CheckOutcome
    {
        /** @var TcpCheckConfig $config */
        $config = $this->configFactory->create($check->getType(), $check->getConfig());
        $start = hrtime(true);

        $socket = @fsockopen($config->host, $config->port, $errno, $error, $check->getTimeoutSeconds());
        $durationMs = intdiv(hrtime(true) - $start, 1_000_000);

        if ($socket === false) {
            return new CheckOutcome(false, $durationMs, $error ?: 'Connection failed');
        }

        fclose($socket);

        return new CheckOutcome(true, $durationMs);
    }
}
