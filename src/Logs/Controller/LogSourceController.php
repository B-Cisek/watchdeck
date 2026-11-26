<?php

declare(strict_types=1);

namespace App\Logs\Controller;

use App\Catalog\Entity\Service;
use App\Logs\Dto\AddedLogSourceResponse;
use App\Logs\Dto\AddLogSourceRequest;
use App\Logs\Dto\LogLineResponse;
use App\Logs\Dto\LogSourceResponse;
use App\Logs\LogSourceCreator;
use App\Logs\Repository\LogLineRepository;
use App\Logs\Repository\LogSourceRepository;
use App\Logs\Vector\VectorConfigGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api', name: 'api_log_sources_', format: 'json')]
final class LogSourceController extends AbstractController
{
    #[Route('/services/{id:service}/log-sources', name: 'list', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function list(Service $service, LogSourceRepository $sources): JsonResponse
    {
        return $this->json(array_map(LogSourceResponse::fromEntity(...), $sources->findOfService($service)));
    }

    /** The newest lines of all the service's containers, oldest first. */
    #[Route('/services/{id:service}/logs', name: 'logs', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function logs(
        Service $service,
        LogLineRepository $lines,
        #[MapQueryParameter(options: ['min_range' => 1, 'max_range' => 1000], validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        int $limit = 500,
    ): JsonResponse {
        return $this->json(array_map(LogLineResponse::fromEntity(...), $lines->findLatestOfService($service, $limit)));
    }

    /** "Add logs" on a service: returns the Vector config file to put on the container's server. */
    #[Route('/services/{id:service}/log-sources', name: 'create', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function create(
        Service $service,
        #[MapRequestPayload(acceptFormat: 'json')]
        AddLogSourceRequest $request,
        LogSourceCreator $creator,
        VectorConfigGenerator $vector,
    ): JsonResponse {
        $source = $creator->create($service, $request);

        return $this->json(
            new AddedLogSourceResponse(LogSourceResponse::fromEntity($source), $vector->generate($source)),
            Response::HTTP_CREATED,
        );
    }
}
