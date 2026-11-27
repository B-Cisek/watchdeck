<?php

declare(strict_types=1);

namespace App\Monitoring\Controller;

use App\Catalog\Entity\Service;
use App\Monitoring\CheckWriter;
use App\Monitoring\Dto\CheckRequest;
use App\Monitoring\Dto\CheckResponse;
use App\Monitoring\Entity\Check;
use App\Monitoring\Repository\CheckRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        #[MapRequestPayload(acceptFormat: 'json')]
        CheckRequest $request,
        CheckWriter $writer,
    ): JsonResponse {
        return $this->json(CheckResponse::fromEntity($writer->create($service, $request)), Response::HTTP_CREATED);
    }

    #[Route('/checks/{id:check}', name: 'update', requirements: ['id' => Requirement::UUID], methods: ['PUT'])]
    public function update(
        Check $check,
        #[MapRequestPayload(acceptFormat: 'json')]
        CheckRequest $request,
        CheckWriter $writer,
    ): JsonResponse {
        return $this->json(CheckResponse::fromEntity($writer->update($check, $request)));
    }

    /** Its results go with it (ON DELETE CASCADE). */
    #[Route('/checks/{id:check}', name: 'delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    public function delete(Check $check, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($check);
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
