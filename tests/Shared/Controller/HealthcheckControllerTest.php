<?php

declare(strict_types=1);

namespace App\Tests\Shared\Controller;

use App\Shared\Controller\HealthcheckController;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;

final class HealthcheckControllerTest extends WebTestCase
{
    public function testIsPublicAndReportsOk(): void
    {
        $client = self::createClient();

        $client->request('GET', '/healthcheck');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","checks":{"app":"ok","database":"ok"}}',
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testReportsUnavailableDatabaseWithoutLeakingDetails(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException(
            new class('password authentication failed for user "app"') extends \RuntimeException implements DbalException {},
        );
        $controller = new HealthcheckController($connection, new NullLogger());
        $controller->setContainer(new Container());

        $response = $controller();

        self::assertSame(503, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status":"error","checks":{"app":"ok","database":"error"}}',
            (string) $response->getContent(),
        );
    }
}
