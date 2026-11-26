<?php

declare(strict_types=1);

namespace App\Logs\Controller;

use App\Logs\Entity\LogSource;
use App\Logs\LogIngestor;
use App\Logs\Vector\VectorPayloadParser;
use Doctrine\DBAL\Exception\ConnectionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Receives batches from Vector's http sink. Vector retries 408, 429 and 5xx only, hence:
 * 200 batch taken (bad events skipped), 400 nothing readable, 404 unknown source, 503 database down.
 *
 * TODO: no authentication yet; anyone who knows a source id can write to it.
 */
final class IngestLogsController extends AbstractController
{
    #[Route('/api/ingest/logs/{id:source}', name: 'api_ingest_logs', requirements: ['id' => Requirement::UUID], methods: ['POST'], format: 'json')]
    public function __invoke(
        LogSource $source,
        Request $request,
        VectorPayloadParser $parser,
        LogIngestor $ingestor,
    ): JsonResponse {
        $events = $parser->parse($request->getContent());

        try {
            $stored = $ingestor->ingest($source, $events);
        } catch (ConnectionException $e) {
            throw new ServiceUnavailableHttpException(30, 'Database unavailable.', $e);
        }

        return $this->json(['received' => \count($events), 'stored' => $stored]);
    }
}
