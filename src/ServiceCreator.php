<?php

declare(strict_types=1);

namespace App;

use App\Dto\CreateServiceRequest;
use App\Entity\Service;
use App\Entity\ServiceGroup;
use App\Repository\ServiceGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final readonly class ServiceCreator
{
    public function __construct(
        private ServiceGroupRepository $groups,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function create(CreateServiceRequest $request): Service
    {
        $group = $request->groupId === null ? null : $this->findGroup($request, Uuid::fromString($request->groupId));
        $service = new Service(Uuid::v7(), $request->name, $group);

        $this->entityManager->persist($service);
        $this->entityManager->flush();

        return $service;
    }

    private function findGroup(CreateServiceRequest $request, Uuid $groupId): ServiceGroup
    {
        return $this->groups->find($groupId) ?? throw $this->groupNotFound($request);
    }

    /** Same response shape as the violations reported by #[MapRequestPayload]. */
    private function groupNotFound(CreateServiceRequest $request): UnprocessableEntityHttpException
    {
        $message = 'Service group not found.';
        $violations = new ConstraintViolationList([
            new ConstraintViolation($message, $message, [], $request, 'groupId', $request->groupId),
        ]);

        return new UnprocessableEntityHttpException($message, new ValidationFailedException($request, $violations));
    }
}
