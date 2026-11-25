<?php

declare(strict_types=1);

namespace App\Monitoring\Runner;

use App\Monitoring\Config\CheckConfigFactory;
use App\Monitoring\Config\HttpCheckConfig;
use App\Monitoring\Entity\Check;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsTaggedItem('http')]
final readonly class HttpCheckRunner implements CheckRunnerInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CheckConfigFactory $configFactory,
    ) {}

    public function run(Check $check): CheckOutcome
    {
        /** @var HttpCheckConfig $config */
        $config = $this->configFactory->create($check->getType(), $check->getConfig());
        $start = hrtime(true);

        try {
            $response = $this->httpClient->request($config->method, $config->url, [
                'timeout' => $check->getTimeoutSeconds(),      // limit bezczynności
                'max_duration' => $check->getTimeoutSeconds(), // limit całości
                'max_redirects' => 0,
            ]);
            $statusCode = $response->getStatusCode();
            $response->cancel(); // body niepotrzebne
        } catch (TransportExceptionInterface $e) {
            return new CheckOutcome(false, $this->elapsedMs($start), $e->getMessage());
        }

        $success = $statusCode === $config->expectedStatus;

        return new CheckOutcome(
            $success,
            $this->elapsedMs($start),
            $success ? null : sprintf('Expected HTTP %d, got %d', $config->expectedStatus, $statusCode),
            ['statusCode' => $statusCode],
        );
    }

    private function elapsedMs(int $start): int
    {
        return intdiv(hrtime(true) - $start, 1_000_000);
    }
}
