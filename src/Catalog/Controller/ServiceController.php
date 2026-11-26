<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Dto\CreateServiceRequest;
use App\Catalog\Dto\RenameServiceRequest;
use App\Catalog\Dto\ServiceResponse;
use App\Catalog\Entity\Service;
use App\Catalog\Repository\ServiceRepository;
use App\Catalog\ServiceCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

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
        #[MapRequestPayload(acceptFormat: 'json')]
        CreateServiceRequest $request,
        ServiceCreator $creator,
    ): JsonResponse {
        return $this->json(ServiceResponse::fromEntity($creator->create($request)), Response::HTTP_CREATED);
    }

    #[Route('/{id:service}', name: 'rename', requirements: ['id' => Requirement::UUID], methods: ['PATCH'])]
    public function rename(
        Service $service,
        #[MapRequestPayload(acceptFormat: 'json')]
        RenameServiceRequest $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $service->setName($request->name);
        $entityManager->flush();

        return $this->json(ServiceResponse::fromEntity($service));
    }

    /** Its checks and their results go with it (ON DELETE CASCADE). */
    #[Route('/{id:service}', name: 'delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    public function delete(Service $service, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($service);
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
