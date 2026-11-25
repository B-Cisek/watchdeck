<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateServiceGroupRequest;
use App\Dto\ServiceGroupResponse;
use App\Entity\ServiceGroup;
use App\Repository\ServiceGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/service-groups', name: 'api_service_groups_', format: 'json')]
final class ServiceGroupController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ServiceGroupRepository $groups): JsonResponse
    {
        return $this->json(array_map(
            ServiceGroupResponse::fromEntity(...),
            $groups->findBy([], ['name' => 'ASC', 'id' => 'ASC']),
        ));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')] CreateServiceGroupRequest $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $group = new ServiceGroup(Uuid::v7(), $request->name);
        $entityManager->persist($group);
        $entityManager->flush();

        return $this->json(ServiceGroupResponse::fromEntity($group), Response::HTTP_CREATED);
    }
}
