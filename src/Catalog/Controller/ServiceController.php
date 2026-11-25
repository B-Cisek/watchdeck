<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Dto\CreateServiceRequest;
use App\Catalog\Dto\ServiceResponse;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\ServiceCreator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/services', name: 'api_services_', format: 'json')]
final class ServiceController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ServiceRepository $services): JsonResponse
    {
        return $this->json(array_map(ServiceResponse::fromEntity(...), $services->findAllWithGroup()));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')] CreateServiceRequest $request,
        ServiceCreator $creator,
    ): JsonResponse {
        return $this->json(ServiceResponse::fromEntity($creator->create($request)), Response::HTTP_CREATED);
    }
}
