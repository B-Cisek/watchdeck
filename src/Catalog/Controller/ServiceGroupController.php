<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Dto\ServiceGroupRequest;
use App\Catalog\Dto\ServiceGroupResponse;
use App\Catalog\Entity\ServiceGroup;
use App\Catalog\Repository\ServiceGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
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
        #[MapRequestPayload(acceptFormat: 'json')]
        ServiceGroupRequest $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $group = new ServiceGroup(Uuid::v7(), $request->name);
        $entityManager->persist($group);
        $entityManager->flush();

        return $this->json(ServiceGroupResponse::fromEntity($group), Response::HTTP_CREATED);
    }

    #[Route('/{id:group}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    public function update(
        ServiceGroup $group,
        #[MapRequestPayload(acceptFormat: 'json')]
        ServiceGroupRequest $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $group->setName($request->name);
        $entityManager->flush();

        return $this->json(ServiceGroupResponse::fromEntity($group));
    }

    /** Its services stay, without a group (ON DELETE SET NULL). */
    #[Route('/{id:group}', name: 'delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    public function delete(ServiceGroup $group, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($group);
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
