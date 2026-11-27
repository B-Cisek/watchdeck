<?php

declare(strict_types=1);

namespace App\Logs\Controller;

use App\Logs\LogIngestor;
use App\Logs\Security\IngestClient;
use App\Logs\Vector\VectorPayloadParser;
use Doctrine\DBAL\Exception\ConnectionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Receives batches from Vector's http sink. Vector retries 408, 429 and 5xx only, hence:
 * 200 batch taken (bad events skipped), 400 nothing readable, 401 missing or unknown token, 503 database down.
 * The token, checked on the ingest firewall, tells which log source the batch belongs to.
 */
final class IngestLogsController extends AbstractController
{
    #[Route('/api/ingest/logs', name: 'api_ingest_logs', methods: ['POST'], format: 'json')]
    public function __invoke(
        #[CurrentUser]
        IngestClient $client,
        Request $request,
        VectorPayloadParser $parser,
        LogIngestor $ingestor,
    ): JsonResponse {
        $events = $parser->parse($request->getContent());

        try {
            $stored = $ingestor->ingest($client->source, $events);
        } catch (ConnectionException $e) {
            throw new ServiceUnavailableHttpException(30, 'Database unavailable.', $e);
        }

        return $this->json(['received' => \count($events), 'stored' => $stored]);
    }
}
