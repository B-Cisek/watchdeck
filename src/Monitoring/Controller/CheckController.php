<?php

declare(strict_types=1);

namespace App\Monitoring\Controller;

use App\Catalog\Entity\Service;
use App\Monitoring\CheckCreator;
use App\Monitoring\Dto\CheckResponse;
use App\Monitoring\Dto\CreateCheckRequest;
use App\Monitoring\Repository\CheckRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api', name: 'api_checks_', format: 'json')]
final class CheckController extends AbstractController
{
    #[Route('/checks', name: 'list', methods: ['GET'])]
    public function list(CheckRepository $checks): JsonResponse
    {
        return $this->json(array_map(
            CheckResponse::fromEntity(...),
            $checks->findBy([], ['name' => 'ASC', 'id' => 'ASC']),
        ));
    }

    #[Route('/services/{id:service}/checks', name: 'create', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function create(
        Service $service,
        #[MapRequestPayload(acceptFormat: 'json')] CreateCheckRequest $request,
        CheckCreator $creator,
    ): JsonResponse
    {
        return $this->json(CheckResponse::fromEntity($creator->create($service, $request)), Response::HTTP_CREATED);
    }
}
