<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthcheckController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/healthcheck', name: 'api_healthcheck')]
    public function __invoke(): JsonResponse
    {
        $databaseUp = $this->isDatabaseUp();

        $response = $this->json(
            [
                'status' => $databaseUp ? 'ok' : 'error',
                'checks' => [
                    'app' => 'ok',
                    'database' => $databaseUp ? 'ok' : 'error',
                ],
            ],
            $databaseUp ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );

        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function isDatabaseUp(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();

            return true;
        } catch (DbalException $e) {
            $this->logger->error('Healthcheck: database unavailable', ['exception' => $e]);

            return false;
        }
    }
}
