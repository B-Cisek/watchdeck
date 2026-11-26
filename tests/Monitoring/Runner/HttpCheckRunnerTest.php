<?php

declare(strict_types=1);

namespace App\Tests\Monitoring\Runner;

use App\Monitoring\Config\CheckConfigFactory;
use App\Monitoring\Entity\Check;
use App\Monitoring\Runner\CheckOutcome;
use App\Monitoring\Runner\HttpCheckRunner;
use App\Tests\Monitoring\CheckFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 *
 * @coversNothing
 */
final class HttpCheckRunnerTest extends KernelTestCase
{
    public function test_expected_status_is_success(): void
    {
        $outcome = $this->runCheck(
            new MockResponse('ok'),
            CheckFactory::create(['url' => 'https://shop.com/health']),
        );

        self::assertTrue($outcome->success);
        self::assertNull($outcome->error);
        self::assertSame(['statusCode' => 200], $outcome->details);
        self::assertGreaterThanOrEqual(0, $outcome->durationMs);
    }

    public function test_unexpected_status_is_failure(): void
    {
        $outcome = $this->runCheck(
            new MockResponse('', ['http_code' => 502]),
            CheckFactory::create(['url' => 'https://shop.com/health']),
        );

        self::assertFalse($outcome->success);
        self::assertSame('Expected HTTP 200, got 502', $outcome->error);
        self::assertSame(['statusCode' => 502], $outcome->details);
    }

    public function test_error_status_can_be_the_expected_one(): void
    {
        $outcome = $this->runCheck(
            new MockResponse('', ['http_code' => 401]),
            CheckFactory::create(['url' => 'https://shop.com/admin', 'expectedStatus' => 401]),
        );

        self::assertTrue($outcome->success);
    }

    public function test_redirect_status_is_failure(): void
    {
        $outcome = $this->runCheck(
            new MockResponse('', [
                'http_code' => 302,
                'response_headers' => ['Location' => 'https://shop.com/login'],
            ]),
            CheckFactory::create(['url' => 'https://shop.com/health']),
        );

        self::assertFalse($outcome->success);
        self::assertSame('Expected HTTP 200, got 302', $outcome->error);
    }

    public function test_transport_error_is_failure_without_status_code(): void
    {
        $outcome = $this->runCheck(
            new MockResponse('', ['error' => 'Could not resolve host: shop.com']),
            CheckFactory::create(['url' => 'https://shop.com/health']),
        );

        self::assertFalse($outcome->success);
        self::assertStringContainsString('Could not resolve host', (string) $outcome->error);
        self::assertSame([], $outcome->details);
    }

    public function test_request_uses_check_configuration(): void
    {
        $response = new MockResponse('', ['http_code' => 200]);

        $this->runCheck(
            $response,
            CheckFactory::create(['url' => 'https://shop.com/health', 'method' => 'HEAD'], timeoutSeconds: 3),
        );

        self::assertSame('HEAD', $response->getRequestMethod());
        self::assertSame('https://shop.com/health', $response->getRequestUrl());
        self::assertEquals(3, $response->getRequestOptions()['timeout']);
        self::assertEquals(3, $response->getRequestOptions()['max_duration']);
        self::assertSame(0, $response->getRequestOptions()['max_redirects']);
    }

    public function test_request_identifies_itself_as_watchdeck(): void
    {
        $response = new MockResponse('', ['http_code' => 200]);

        $this->runCheck($response, CheckFactory::create(['url' => 'https://shop.com/health']));

        self::assertSame(
            ['User-Agent: '.HttpCheckRunner::USER_AGENT],
            $response->getRequestOptions()['normalized_headers']['user-agent'],
        );
        self::assertStringStartsWith('Watchdeck-Monitor/', HttpCheckRunner::USER_AGENT);
    }

    private function runCheck(MockResponse $response, Check $check): CheckOutcome
    {
        $runner = new HttpCheckRunner(
            new MockHttpClient($response),
            self::getContainer()->get(CheckConfigFactory::class),
        );

        return $runner->run($check);
    }
}
